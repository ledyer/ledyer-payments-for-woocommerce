# Development

## Code Standards

This project uses PHP_CodeSniffer to enforce WordPress coding standards. To get started:

### Prerequisites

- PHP 7.4 or higher
- [Composer](https://getcomposer.org/download/) installed globally

### Setup

1. Clone the repository
2. Install dependencies:
   ```bash
   composer install
   ```

### Local development

Change PHP version in .env or set env variable PHP_VERSION

```shell
docker-compose up --build
```

### Using Code Standards Tools

The project includes two composer scripts for code standards:

1. **Check code standards**:
   ```bash
   composer phpcs
   ```
   This will scan your code and report any coding standards violations.

2. **Fix code standards automatically**:
   ```bash
   composer phpcbf
   ```
   This will automatically fix coding standards issues that can be fixed automatically.

You can also target specific files or directories:

```bash
composer phpcs -- src/specific-directory
composer phpcbf -- src/specific-file.php
```

Note that not all issues can be fixed automatically with phpcbf. After running it, you should run phpcs again to check for any remaining issues that need manual fixing.

