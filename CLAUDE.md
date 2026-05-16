# Quorae Settings Bundle

Symfony 7+ bundle providing YAML-defined runtime settings with DB overrides, encryption, caching, validation, and an optional Live Component admin UI.

## Project Structure

```
src/
├── Command/           Console commands (cache, list, clear, check-encryption)
├── Contract/          Public interfaces (SettingsReaderInterface, WriterInterface, etc.)
├── Dto/               Value objects (SettingsGroup, SettingFieldDefinition, etc.)
├── Entity/            Doctrine entity (SettingOverride)
├── Handler/           Use-case handlers (IndexSettings, UpdateSettings)
├── Repository/        Doctrine repository
├── Service/           Core services (Manager, Caster, Cryptor, DefinitionLoader, etc.)
├── DependencyInjection/
└── QuoraeSettingsBundle.php
config/               Doctrine mapping (XML)
templates/            Twig templates for Live Component UI (optional)
```

## Architecture

**Flow**: Handler → Service (SettingsManager) → Repository → Entity

**Public API** (Contract/):
- `SettingsReaderInterface` — read settings values
- `SettingsWriterInterface` — persist overrides
- `SettingOverrideRepositoryInterface` — storage abstraction
- `SettingsDefinitionProviderInterface` — YAML schema access

**Key services**:
- `SettingsManager` — orchestrates read/write with caching
- `SettingsDefinitionLoader` — parses YAML definitions
- `SettingCryptor` — handles encrypted fields
- `SettingsCaster` — type casting for setting values

## Coding Standards

- PHP 8.3+ with `declare(strict_types=1)` everywhere
- Final readonly for DTOs and Handlers
- Interfaces for all public API (Contract/ directory)
- Constructor DI only
- PHPStan level 8

## Quality Commands

```bash
vendor/bin/phpstan analyse        # Static analysis
vendor/bin/phpunit                # Unit tests
vendor/bin/php-cs-fixer check     # Code style check
vendor/bin/php-cs-fixer fix       # Code style fix
```

## Design Principles

- Optional dependencies MUST stay optional (Live Components, Security)
- No AICD-specific assumptions — bundle is generic
- Keep backward compatibility on public interfaces
- TDD for all new features

## Git Workflow

- Branch: `main` only (no environments — it's a library)
- Commit format: conventional commits
- Branch prefix: `feat/`, `fix/`, `refactor/`, `chore/`

## Workflow Entry Point

Invoke `/workflow-orchestrator` for any implementation task.

## Lessons Learned

@.claude/tasks/lessons.md
