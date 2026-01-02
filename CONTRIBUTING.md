# Contributing to SAP B1 Laravel SDK

Thank you for considering contributing to the SAP B1 Laravel SDK! This document provides guidelines for contributing to this project.

## Development Setup

1. Fork the repository
2. Clone your fork:
   ```bash
   git clone https://github.com/YOUR_USERNAME/sapb1-laravel-sdk.git
   cd sapb1-laravel-sdk
   ```
3. Install dependencies:
   ```bash
   composer install
   ```
4. Run tests:
   ```bash
   composer test
   ```

## Code Style

This project uses Laravel Pint for code formatting. Before submitting a PR, run:

```bash
composer format
```

## Static Analysis

We use PHPStan at level 8. Ensure your code passes:

```bash
composer analyse
```

## Pull Request Process

1. Create a new branch for your feature/fix:
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. Make your changes and write tests if applicable

3. Ensure all tests pass:
   ```bash
   composer test
   ```

4. Ensure code style is correct:
   ```bash
   composer format
   ```

5. Ensure static analysis passes:
   ```bash
   composer analyse
   ```

6. Commit your changes with a descriptive message:
   ```bash
   git commit -m "Add: description of your changes"
   ```

7. Push to your fork and submit a Pull Request

## Commit Message Guidelines

Use conventional commit messages:

- `feat:` - New feature
- `fix:` - Bug fix
- `docs:` - Documentation changes
- `test:` - Adding or updating tests
- `refactor:` - Code refactoring
- `chore:` - Maintenance tasks

Example:
```
feat: add batch operations support
fix: correct session expiry calculation
docs: update OData query examples
```

## Reporting Issues

When reporting issues, please include:

- PHP version
- Laravel version
- SAP B1 Service Layer version (if known)
- Steps to reproduce
- Expected behavior
- Actual behavior

## Feature Requests

Feature requests are welcome! Please provide:

- Clear description of the feature
- Use case / why it's needed
- Possible implementation approach (optional)

## Code of Conduct

Be respectful and constructive in all interactions. We're all here to build something useful together.

## Questions?

Feel free to open an issue for questions or discussions.
