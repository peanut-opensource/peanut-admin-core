# P1-OVR03 SMS Provider Host Consumption Contract

## Status

```text
state: implemented
prerequisite_commit: 694a6e26708d47949f3176d07064cfd1bfc5a90a
packages: peanut-admin/core
runtime_operations: none
qualification_status: candidate-only
```

## Objective

Prove the first real PHP application override chain by moving the reference
Host's SMS provider selection to the accepted `ServiceOverrideRegistry`. The
notification package declares one stable service slot, the ThinkPHP Host binds
the resolved interface map during startup, and `NotificationRuntimeFactory`
requests `SmsProvider` from the container instead of choosing or constructing a
provider itself.

This task turns P1-OVR02 from an unused registry into one concrete Host
consumption path. It does not publish the package or authorize downstream
application consumption.

## Service Slot

The package-owned slot is fixed as:

```text
key: peanut.notification.service.sms-provider
contract: PeanutAdmin\NotificationSms\Sms\SmsProvider
contract_version: 1.0.0
default: PeanutAdmin\NotificationSms\Sms\DisabledSmsProvider
```

`DisabledSmsProvider` performs no network request and fails permanently with
the safe code `SMS_PROVIDER_UNAVAILABLE`. This is the fail-closed package
default. `LocalDevSmsProvider` remains an explicit development implementation
and must not become the implicit production fallback.

## Host Configuration And Wiring

The application owns `backend/config/service-overrides.php`. It returns a list
of `ServiceOverride` objects and is the only service-implementation selection
authority in this Host. An empty list selects every package default. The
reference Host may read `PEANUT_SMS_PROVIDER_IMPLEMENTATION` as a fully
qualified application implementation class and construct the matching SMS
override declaration; an empty value means no override.

`AppService::register()` must:

1. load and validate the application override list;
2. construct one immutable `ServiceOverrideRegistry` with the SMS slot;
3. register that registry instance in the ThinkPHP container;
4. bind every resolved `contract => implementation` pair;
5. fail application startup when configuration or registry validation fails.

`NotificationRuntimeFactory::worker()` may retain an explicit optional
`SmsProvider` parameter for focused tests. Without that argument it must request
`SmsProvider` from ThinkPHP's container. It must not read the override registry,
inspect provider keys, or instantiate `LocalDevSmsProvider` directly.

The legacy `provider` entry and `PEANUT_SMS_PROVIDER` selector are removed from
`backend/config/notification-sms.php`; envelope and recipient configuration
remain owned there. No alias, dual configuration, compatibility fallback, or
provider-name switch is permitted.

## Security And Failure Semantics

- Missing configuration resolves to the disabled package implementation.
- Unknown classes, non-implementing classes, duplicate keys, contract mismatch,
  or version mismatch fail during Host registration.
- Registry diagnostics contain class names and selection source only; they must
  not contain provider credentials, recipient data, phone numbers, task
  payloads, or runtime secrets.
- Provider selection does not alter Tenant context, permissions, task envelope
  validation, rate limits, idempotency, receipt validation, or retry handling.

## Non-Goals

- No real SMS provider SDK, credential schema, provider marketplace, dynamic
  code loading, container abstraction, or Module discovery change.
- No API, OpenAPI, generated client, database, migration, route, UI, or Runtime
  operation change.
- No starter propagation, application-repository migration, registry
  publication, tag, release, or fixed-candidate qualification.
- No change to the two-public-package boundary or `0.1.0-alpha.1` publication
  candidate.

## Implementation Task

The implementation may change only:

- `packages/php/notification-sms/src/Sms/DisabledSmsProvider.php`;
- `packages/php/notification-sms/README.md`;
- `backend/config/service-overrides.php`;
- `backend/config/notification-sms.php`;
- `backend/app/AppService.php`;
- `backend/app/notification/NotificationRuntimeFactory.php`;
- `backend/tests/Smoke/ServiceOverrideHostWiringTest.php`;
- `docs/status/index.md` for candidate status only;
- this contract only for recording the implementation commit.

The implementation must not change package manifests, Composer locks,
dependencies, existing interface signatures, other provider factories,
starter files, schemas, OpenAPI, generated artifacts, Runtime coverage, package
version, publication records, or P1-OVR01/P1-OVR02 source.

## Verification Ownership

The implementation owner performs static review, verifies the exact write set,
runs `git diff --check`, and runs this focused Host test once with the repository
PHP 8.3 toolchain:

```bash
vendor/bin/phpunit backend/tests/Smoke/ServiceOverrideHostWiringTest.php
```

The test owns disabled-default resolution, explicit application override
resolution, container binding, registry source metadata, invalid override
startup failure, and the factory's container consumption path. Package unit,
database, HTTP, browser, build, aggregate, publication, and downstream-consumer
checks remain deferred to a later fixed-candidate qualification.

## Stop Line

OVR03 is an unqualified post-`alpha.1` candidate. It must not be merged into the
fixed publication branch, published, tagged, represented as Peanut Admin
application consumption, or used to move the downstream lock until a later
fixed-tree qualification and explicit downstream decision approve it.
