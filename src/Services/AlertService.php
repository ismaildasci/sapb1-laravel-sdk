<?php

declare(strict_types=1);

namespace SapB1\Services;

use Illuminate\Support\Collection;
use SapB1\Client\Response;
use SapB1\Client\SapB1Client;

class AlertService
{
    public function __construct(
        protected SapB1Client $client,
        protected string $connection = 'default'
    ) {}

    /**
     * Send an internal message to SAP B1 users.
     *
     * @param  array<string, mixed>  $data
     */
    public function send(array $data): Response
    {
        $payload = [
            'MessageDataColumns' => $data['columns'] ?? [],
            'MessageDataLines' => $data['lines'] ?? [],
            'RecipientCollection' => $this->formatRecipients($data['recipients'] ?? []),
            'Subject' => $data['subject'] ?? '',
            'Text' => $data['message'] ?? $data['text'] ?? '',
        ];

        return $this->client
            ->connection($this->connection)
            ->create('Messages', $payload);
    }

    /**
     * Send a simple message to users.
     *
     * @param  array<int, string>  $recipients
     */
    public function sendMessage(array $recipients, string $subject, string $message): Response
    {
        return $this->send([
            'recipients' => $recipients,
            'subject' => $subject,
            'message' => $message,
        ]);
    }

    /**
     * Get all alert configurations.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function configurations(): Collection
    {
        $response = $this->client
            ->connection($this->connection)
            ->get('AlertManagements');

        if ($response->failed()) {
            return collect();
        }

        return collect($response->value());
    }

    /**
     * Get a specific alert configuration.
     *
     * @return array<string, mixed>|null
     */
    public function configuration(int $id): ?array
    {
        $response = $this->client
            ->connection($this->connection)
            ->find('AlertManagements', $id);

        if ($response->failed()) {
            return null;
        }

        return $response->entity();
    }

    /**
     * Create an alert configuration.
     *
     * @param  array<string, mixed>  $data
     */
    public function createRule(array $data): Response
    {
        $payload = [
            'Name' => $data['name'],
            'Active' => $data['active'] ?? 'tYES',
            'Priority' => $data['priority'] ?? 'atp_Normal',
            'FrequencyType' => $data['frequency_type'] ?? $data['frequency'] ?? 'atfi_Minutes',
            'FrequencyInterval' => $data['frequency_interval'] ?? 60,
            'AlertManagementRecipients' => $this->formatAlertRecipients($data['recipients'] ?? []),
        ];

        if (isset($data['query_id'])) {
            $payload['QueryID'] = $data['query_id'];
        }

        return $this->client
            ->connection($this->connection)
            ->create('AlertManagements', $payload);
    }

    /**
     * Update an alert configuration.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateRule(int $id, array $data): Response
    {
        return $this->client
            ->connection($this->connection)
            ->update('AlertManagements', $id, $data);
    }

    /**
     * Delete an alert configuration.
     */
    public function deleteRule(int $id): Response
    {
        return $this->client
            ->connection($this->connection)
            ->delete('AlertManagements', $id);
    }

    /**
     * Get pending alerts for current user.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function pending(): Collection
    {
        try {
            $result = $this->client
                ->connection($this->connection)
                ->service('Alerts')
                ->where('Status', 'atNew')
                ->get();

            /** @var array<int, array<string, mixed>> $records */
            $records = $result['value'] ?? $result;

            return collect($records);
        } catch (\Throwable) {
            return collect();
        }
    }

    /**
     * Mark an alert as read.
     */
    public function markRead(int $alertId): Response
    {
        return $this->client
            ->connection($this->connection)
            ->update('Alerts', $alertId, ['Status' => 'atRead']);
    }

    /**
     * Format recipients for message.
     *
     * @param  array<int, string>  $recipients
     * @return array<int, array<string, mixed>>
     */
    protected function formatRecipients(array $recipients): array
    {
        return array_map(fn (string $user) => [
            'UserCode' => $user,
            'SendInternal' => 'tYES',
        ], $recipients);
    }

    /**
     * Format recipients for alert configuration.
     *
     * @param  array<int, string>  $recipients
     * @return array<int, array<string, mixed>>
     */
    protected function formatAlertRecipients(array $recipients): array
    {
        return array_map(fn (string $user) => [
            'UserCode' => $user,
            'SendInternal' => 'tYES',
            'SendEmail' => 'tNO',
            'SendSMS' => 'tNO',
            'SendFax' => 'tNO',
        ], $recipients);
    }

    /**
     * Use a different connection.
     */
    public function connection(string $connection): self
    {
        $clone = clone $this;
        $clone->connection = $connection;

        return $clone;
    }
}
