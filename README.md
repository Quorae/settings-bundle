# QuoraeSettingsBundle

YAML-defined runtime settings with DB overrides, libsodium encryption, cache, validation, and a Live Component admin UI for Symfony 7+.

## Features

- **YAML definitions** — declare settings groups in `config/settings/*.yaml` with typed fields, labels, descriptions, defaults, validation rules, and choices
- **DB overrides** — override any default at runtime via a `setting_overrides` table (only stores diffs from defaults)
- **Encryption** — fields marked `encrypted: true` are stored with libsodium authenticated encryption (XSalsa20-Poly1305), key derived from `APP_SECRET` via HKDF
- **Env var resolution** — defaults can be sourced from environment variables (`env_key:`) or files (`file://`)
- **Cache** — resolved groups are cached via `TagAwareCacheInterface` with per-request memoization
- **Validation** — explicit constraints from YAML (`NotBlank`, `Length`, `Range`, `Choice`, `Regex`, `Type`, `Email`, `Url`) + implicit shape guards per field type
- **Live Component editor** — drop-in `<twig:QuoraeSettings:Editor group="my_group" />` with per-field rendering, masked passwords, reset-to-default, flash messages
- **CLI commands** — `quorae:settings:list`, `quorae:settings:cache`, `quorae:settings:clear`, `quorae:settings:check-encryption`
- **Multi-DB support** — repository uses platform-aware upsert (PostgreSQL, MySQL/MariaDB, generic fallback)

## Installation

```bash
composer require quorae/settings-bundle
```

Register the bundle (auto-discovered by Symfony Flex):

```php
// config/bundles.php
return [
    // ...
    Quorae\SettingsBundle\QuoraeSettingsBundle::class => ['all' => true],
];
```

### Database

Run `bin/console doctrine:schema:update --force` or create a migration for the `setting_overrides` table. The entity is mapped via Doctrine ORM attributes.

### Cache

The bundle uses `cache.app.taggable`. Make sure your Symfony app has a tag-aware cache pool configured (default in Symfony 7).

## Configuration

```yaml
# config/packages/quorae_settings.yaml
quorae_settings:
    definitions_path: config/settings          # relative to %kernel.project_dir%
    scope: global                              # only allowed scope (V1)
    allowed_env_prefixes: ['AI_', 'MAILER_']   # restrict env_key usage (empty = all allowed)
    allowed_file_prefixes: ['docs/prompts/']   # restrict file:// defaults (empty = all allowed)
    encryption:
        hkdf_info: my-app-settings             # HKDF context — change to isolate from other apps
```

## Defining a settings group

Create a YAML file in `config/settings/`:

```yaml
# config/settings/mailer.yaml
group: mailer
group_label: "Mail configuration"
scope: global
order: 100

fields:
  host:
    type: text
    label: "SMTP host"
    default: "smtp.example.com"
    rules:
      - NotBlank: ~

  port:
    type: integer
    label: "SMTP port"
    default: 587
    rules:
      - Range: { min: 1, max: 65535 }

  password:
    type: password
    label: "SMTP password"
    encrypted: true
    env_key: MAILER_PASSWORD

  use_tls:
    type: boolean
    label: "Use TLS"
    default: true

  provider:
    type: choice
    label: "Provider"
    default: smtp
    choices:
      smtp: "SMTP direct"
      sendgrid: "SendGrid API"
      ses: "Amazon SES"
```

### Field types

`text`, `textarea`, `choice`, `boolean`, `integer`, `float`, `password`, `markdown`

### Default resolution priority

`env_key` > `file://` > literal `default`

## Reading settings

```php
use Quorae\SettingsBundle\Contract\SettingsReaderInterface;

final readonly class MyService
{
    public function __construct(
        private SettingsReaderInterface $settings,
    ) {}

    public function doSomething(): void
    {
        $host = $this->settings->getValue('mailer', 'host');
        $group = $this->settings->getGroup('mailer');
        $port = $group->port; // magic __get
    }
}
```

## Admin UI (Live Component)

Requires `symfony/ux-live-component` and `symfony/ux-twig-component`.

```twig
{# In any Twig template #}
<twig:QuoraeSettings:Editor group="mailer" />
```

Templates are overridable via standard Symfony mechanism:
```
templates/bundles/QuoraeSettingsBundle/components/QuoraeSettings/SettingsEditor.html.twig
```

The component has no authorization by default. Protect it in your app:
- Use `#[IsGranted]` on your controller
- Or extend the component class and add `#[IsGranted('ROLE_ADMIN')]`

## CLI Commands

```bash
bin/console quorae:settings:list                     # show all resolved values
bin/console quorae:settings:list --group=mailer       # single group
bin/console quorae:settings:cache                     # warm cache
bin/console quorae:settings:clear                     # purge cache
bin/console quorae:settings:check-encryption          # verify encrypted fields
```

## License

MIT
