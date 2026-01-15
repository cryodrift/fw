# System Tools

This directory contains utility tools for system management and operations within the application framework.

## Overview

The `sys/tool` directory provides various utility classes and functions for:
- Command-line interface (CLI) operations
- System installation and configuration
- Package management (Composer, NPM)
- File operations (ZIP, download)
- PHP Phar creation and manipulation
- HTML/CSS utilities
- Environment setup and management
- Server management

## Components

### CLI Tool (`Cli.php`)

The main command-line interface handler with various functions:

#### Configuration
- `config`: Manage system configuration
- `env`: Environment configuration management
- `configVars`: Configuration variable management
- `configMeta`: Configuration metadata management

#### Installation
- `install`: Install system components
- `modules`: Manage system modules
- `composer`: Manage Composer packages
- `npm`: Manage NPM packages

#### Testing
- `tests`: Run system tests

#### File Operations
- `zip`: Create and manage ZIP archives
- `down`: Download operations

#### Version Control
- `git`: Git operations

#### PHP Phar Management
- `pharcreate`: Create PHP Phar archives
- `pharadd`: Add files to Phar archives
- `pharshow`: Display Phar archive contents
- `pharextract`: Extract files from Phar archives

#### Server Management
- `serv`: Manage the development server

#### Utilities
- `vars`: Display system variables
- `help`: Display help information

### HTML Utilities

- `HtmlClassExtractor`: Extract CSS class names from HTML content

### Other Utilities

- `Echoblocker`: Utility for blocking echo output
- `DbHelperStatic`: Database helper functions
- `Configmeta`: Configuration metadata utilities

## Usage

Most tools in this directory are accessed through the CLI interface:

```
php index.php /sys/cli [command] [options]
```

For example:
```
php index.php /sys/cli help
php index.php /sys/cli install -dir="./vendor"
php index.php /sys/cli config
```

## Configuration

The base configuration for these tools is defined in `base-config.php`, which sets up:
- Router configurations
- Static file routes
- Dependency injection settings

## Development

When extending the tools in this directory, ensure that:
1. New tools implement the appropriate interfaces
2. Configuration is updated in base-config.php if needed
3. CLI help documentation is updated
