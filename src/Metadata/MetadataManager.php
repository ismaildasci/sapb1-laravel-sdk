<?php

declare(strict_types=1);

namespace SapB1\Metadata;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use SapB1\Client\SapB1Client;

class MetadataManager
{
    protected const CACHE_KEY_PREFIX = 'sap_b1_metadata:';

    protected const CACHE_TTL = 3600; // 1 hour

    /**
     * @var array<string, EntitySchema>
     */
    protected array $schemas = [];

    /**
     * @var array<string, array<string, mixed>>|null
     */
    protected ?array $rawMetadata = null;

    public function __construct(
        protected SapB1Client $client,
        protected string $connection = 'default'
    ) {}

    /**
     * Get all available entity names.
     *
     * @return list<string>
     */
    public function entities(): array
    {
        $metadata = $this->getMetadata();

        /** @var array<int|string, mixed> $entities */
        $entities = $metadata['entities'] ?? [];

        $names = [];
        foreach (array_keys($entities) as $key) {
            $names[] = (string) $key;
        }

        return $names;
    }

    /**
     * Get schema for a specific entity.
     */
    public function entity(string $name): ?EntitySchema
    {
        if (isset($this->schemas[$name])) {
            return $this->schemas[$name];
        }

        $metadata = $this->getMetadata();
        $entityData = $metadata['entities'][$name] ?? null;

        if ($entityData === null) {
            return null;
        }

        $schema = $this->buildEntitySchema($name, $entityData);
        $this->schemas[$name] = $schema;

        return $schema;
    }

    /**
     * Check if an entity exists.
     */
    public function hasEntity(string $name): bool
    {
        return in_array($name, $this->entities(), true);
    }

    /**
     * Check if an entity has a specific field.
     */
    public function hasField(string $entity, string $field): bool
    {
        $schema = $this->entity($entity);

        return $schema?->hasField($field) ?? false;
    }

    /**
     * Get all User Defined Objects.
     *
     * @return Collection<string, EntitySchema>
     */
    public function udos(): Collection
    {
        return collect($this->entities())
            ->map(fn (string $name) => $this->entity($name))
            ->filter(fn (?EntitySchema $schema) => $schema !== null && $schema->isUdo)
            ->keyBy('name');
    }

    /**
     * Get all User Defined Tables.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function udts(): Collection
    {
        $response = $this->client
            ->connection($this->connection)
            ->get('UserTablesMD');

        if ($response->failed()) {
            return collect();
        }

        return collect($response->value());
    }

    /**
     * Get User Defined Fields for an entity.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function udfs(string $tableName): Collection
    {
        try {
            $result = $this->client
                ->connection($this->connection)
                ->service('UserFieldsMD')
                ->where('TableName', $tableName)
                ->get();

            /** @var array<int, array<string, mixed>> $records */
            $records = $result['value'] ?? $result;

            return collect($records);
        } catch (\Throwable) {
            return collect();
        }
    }

    /**
     * Search for entities by name pattern.
     *
     * @return array<int, string>
     */
    public function search(string $pattern): array
    {
        $pattern = strtolower($pattern);

        return array_filter(
            $this->entities(),
            fn (string $name) => str_contains(strtolower($name), $pattern)
        );
    }

    /**
     * Get the raw metadata from Service Layer.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        if ($this->rawMetadata !== null) {
            return $this->rawMetadata;
        }

        $cacheKey = self::CACHE_KEY_PREFIX.$this->connection;

        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            $this->rawMetadata = $cached;

            return $cached;
        }

        $this->rawMetadata = $this->fetchMetadata();

        Cache::put($cacheKey, $this->rawMetadata, self::CACHE_TTL);

        return $this->rawMetadata;
    }

    /**
     * Refresh metadata from Service Layer.
     *
     * @return array<string, mixed>
     */
    public function refresh(): array
    {
        $this->rawMetadata = null;
        $this->schemas = [];

        Cache::forget(self::CACHE_KEY_PREFIX.$this->connection);

        return $this->getMetadata();
    }

    /**
     * Fetch metadata from Service Layer.
     *
     * @return array<string, mixed>
     */
    protected function fetchMetadata(): array
    {
        // Fetch the main metadata document
        $response = $this->client
            ->connection($this->connection)
            ->get('$metadata');

        $metadata = [
            'entities' => [],
            'enums' => [],
            'version' => null,
        ];

        if ($response->failed()) {
            return $metadata;
        }

        $content = $response->body();

        // Parse XML metadata
        if (str_starts_with(trim($content), '<?xml') || str_starts_with(trim($content), '<edmx:')) {
            return $this->parseXmlMetadata($content);
        }

        // Parse JSON metadata (OData v4)
        $json = json_decode($content, true);
        if (is_array($json)) {
            return $this->parseJsonMetadata($json);
        }

        return $metadata;
    }

    /**
     * Parse XML metadata (OData v3).
     *
     * @return array<string, mixed>
     */
    protected function parseXmlMetadata(string $xml): array
    {
        $metadata = [
            'entities' => [],
            'enums' => [],
            'version' => 'v3',
        ];

        try {
            // Suppress warnings for invalid XML
            libxml_use_internal_errors(true);
            $doc = simplexml_load_string($xml);
            libxml_clear_errors();

            if ($doc === false) {
                return $metadata;
            }

            // Register namespaces
            $namespaces = $doc->getNamespaces(true);
            $doc->registerXPathNamespace('edmx', $namespaces['edmx'] ?? 'http://schemas.microsoft.com/ado/2007/06/edmx');
            $doc->registerXPathNamespace('edm', $namespaces[''] ?? 'http://schemas.microsoft.com/ado/2008/09/edm');

            // Extract EntityTypes
            $entityTypes = $doc->xpath('//edm:EntityType') ?: [];
            foreach ($entityTypes as $entityType) {
                $name = (string) $entityType['Name'];

                $fields = [];
                $keyField = null;

                // Get key
                $keys = $entityType->xpath('edm:Key/edm:PropertyRef');
                if (! empty($keys)) {
                    $keyField = (string) $keys[0]['Name'];
                }

                // Get properties
                $properties = $entityType->xpath('edm:Property') ?: [];
                foreach ($properties as $prop) {
                    $propName = (string) $prop['Name'];
                    $isUdf = str_starts_with($propName, 'U_');

                    $fieldInfo = new FieldInfo(
                        name: $propName,
                        type: (string) ($prop['Type'] ?? 'Edm.String'),
                        nullable: ((string) ($prop['Nullable'] ?? 'true')) !== 'false',
                        isKey: $propName === $keyField,
                        isUdf: $isUdf,
                        maxLength: isset($prop['MaxLength']) ? (int) $prop['MaxLength'] : null,
                    );

                    if ($isUdf) {
                        $fields['udfs'][$propName] = $fieldInfo;
                    } else {
                        $fields['standard'][$propName] = $fieldInfo;
                    }
                }

                // Get navigation properties
                $navProps = [];
                $navProperties = $entityType->xpath('edm:NavigationProperty') ?: [];
                foreach ($navProperties as $navProp) {
                    $navProps[(string) $navProp['Name']] = (string) ($navProp['Type'] ?? '');
                }

                $metadata['entities'][$name] = [
                    'name' => $name,
                    'fields' => $fields['standard'] ?? [],
                    'udfs' => $fields['udfs'] ?? [],
                    'navigation' => $navProps,
                    'key' => $keyField,
                    'is_udo' => str_starts_with($name, 'U_'),
                ];
            }

            // Extract EntitySets (the actual endpoint names)
            $entitySets = $doc->xpath('//edm:EntitySet') ?: [];
            $entitySetMap = [];
            foreach ($entitySets as $entitySet) {
                $setName = (string) $entitySet['Name'];
                $entityTypeName = (string) $entitySet['EntityType'];
                // Extract just the type name from namespace.TypeName
                $typeName = substr($entityTypeName, strrpos($entityTypeName, '.') + 1);
                $entitySetMap[$typeName] = $setName;
            }

            // Map EntityTypes to EntitySets
            $remapped = [];
            foreach ($metadata['entities'] as $typeName => $data) {
                $endpointName = $entitySetMap[$typeName] ?? $typeName;
                $data['endpoint'] = $endpointName;
                $remapped[$endpointName] = $data;
            }
            $metadata['entities'] = $remapped;

        } catch (\Exception) {
            // Return empty metadata on parse error
        }

        return $metadata;
    }

    /**
     * Parse JSON metadata (OData v4).
     *
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    protected function parseJsonMetadata(array $json): array
    {
        $metadata = [
            'entities' => [],
            'enums' => [],
            'version' => 'v4',
        ];

        // OData v4 JSON metadata structure
        $schemas = $json['$Schema'] ?? $json['schemas'] ?? [];

        foreach ($schemas as $schema) {
            $entityTypes = $schema['EntityType'] ?? $schema['entityTypes'] ?? [];

            foreach ($entityTypes as $name => $definition) {
                if (is_int($name)) {
                    $name = $definition['$Name'] ?? $definition['name'] ?? "Entity{$name}";
                }

                $fields = [];
                $udfs = [];
                $keyField = null;

                // Get key
                $keys = $definition['$Key'] ?? $definition['key'] ?? [];
                if (! empty($keys)) {
                    $keyField = is_array($keys[0]) ? ($keys[0]['$Name'] ?? null) : $keys[0];
                }

                // Get properties
                $properties = $definition['Property'] ?? $definition['properties'] ?? [];
                foreach ($properties as $propName => $propDef) {
                    if (is_int($propName)) {
                        $propName = $propDef['$Name'] ?? $propDef['name'] ?? "Prop{$propName}";
                    }

                    $isUdf = str_starts_with($propName, 'U_');

                    $fieldInfo = new FieldInfo(
                        name: $propName,
                        type: $propDef['$Type'] ?? $propDef['type'] ?? 'Edm.String',
                        nullable: ($propDef['$Nullable'] ?? $propDef['nullable'] ?? true) !== false,
                        isKey: $propName === $keyField,
                        isUdf: $isUdf,
                        maxLength: $propDef['$MaxLength'] ?? $propDef['maxLength'] ?? null,
                    );

                    if ($isUdf) {
                        $udfs[$propName] = $fieldInfo;
                    } else {
                        $fields[$propName] = $fieldInfo;
                    }
                }

                $metadata['entities'][$name] = [
                    'name' => $name,
                    'fields' => $fields,
                    'udfs' => $udfs,
                    'navigation' => [],
                    'key' => $keyField,
                    'is_udo' => str_starts_with($name, 'U_'),
                ];
            }
        }

        return $metadata;
    }

    /**
     * Build an EntitySchema from metadata.
     *
     * @param  array<string, mixed>  $data
     */
    protected function buildEntitySchema(string $name, array $data): EntitySchema
    {
        return new EntitySchema(
            name: $name,
            entityType: $data['endpoint'] ?? $name,
            fields: $data['fields'] ?? [],
            userDefinedFields: $data['udfs'] ?? [],
            navigationProperties: $data['navigation'] ?? [],
            keyField: $data['key'] ?? null,
            isUdo: $data['is_udo'] ?? str_starts_with($name, 'U_'),
        );
    }

    /**
     * Use a different connection.
     */
    public function connection(string $connection): self
    {
        $clone = clone $this;
        $clone->connection = $connection;
        $clone->rawMetadata = null;
        $clone->schemas = [];

        return $clone;
    }
}
