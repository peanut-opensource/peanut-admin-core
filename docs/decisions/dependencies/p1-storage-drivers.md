# P1 Storage Driver Dependency Decision

## Status

```text
decision: P1-STORAGE-DRIVER-DEPENDENCIES
state: accepted
reviewed_at: 2026-09-07
core_prerequisite_commit: cefb050002b455747c20bb0790864d6f50eb24d8
application_evidence_commit: f74d841b4e084dbb8b5ec4a2d6312494042b0d10
public_package: peanut-admin/core
core_runtime_install_authorized: false
host_runtime_install_authorized: true for a selected provider only
publication_authorized: false
```

The machine-readable decision is
[`p1-storage-drivers.json`](./p1-storage-drivers.json). The decision accepts
three optional provider SDK integration points. It does not add those SDKs to
the Core aggregate lock or force an object-storage provider on every consumer.

## Accepted Optional Provider SDKs

| Package | Reviewed version | Host constraint | License | Purpose |
| --- | --- | --- | --- | --- |
| `aliyuncs/oss-sdk-php` | `2.7.3` | `^2.4` | MIT | Host-assembled Aliyun OSS client used by `AliyunStorageDriver`. |
| `qcloud/cos-sdk-v5` | `2.6.17` | `^2.5` | MIT | Host-assembled Tencent COS client used by `QcloudStorageDriver`. |
| `qiniu/php-sdk` | `7.4.0` | `^7.4` | MIT | Host-assembled Qiniu authentication used by `QiniuStorageDriver`. |

Each SDK is an explicit direct dependency of a Host that selects that provider.
Core lists it under Composer `suggest` and references its concrete public type
only in the corresponding optional driver. Core must not obtain an SDK through
an undeclared transitive dependency. A consumer that does not install the SDK
cannot construct or use that driver; local storage remains SDK-free.

## Boundary And Alternatives

The Host owns credentials, SDK construction, provider configuration, timeout
configuration and observability middleware. Aliyun and Tencent drivers receive
their concrete SDK clients. Qiniu receives the concrete `Qiniu\Auth` object and
a narrow first-party `StorageHttpTransport`; this retains the Host's common
outbound HTTP policy without making its DTO or HTTP library part of Core.

Flysystem was not selected because this extraction preserves four already used
operations and provider-specific private ACL behavior. Adding a filesystem
abstraction would force a new dependency on all Core consumers and still need
provider adapters. Reimplementing vendor authentication or signing was rejected
because mature official SDKs already own those protocols. A shared invented
provider client interface was rejected because the three SDKs expose materially
different operations and lifecycle rules.

Replacement keeps `StorageDriver` stable, adds a new Host adapter, migrates
provider configuration explicitly and removes the old driver plus its Host SDK
requirement after stored objects have moved. Removing all cloud providers deletes
the optional drivers and Composer suggestions; it does not affect local storage
or the higher-level File Media storage provider contract.

## Security And Lock Evidence

Package identity, MIT license, source reference and the exact reviewed versions
were taken from the application's committed Composer lock at
`f74d841b4e084dbb8b5ec4a2d6312494042b0d10`. Core stores no provider credential,
does not log signed URLs and retains private upload ACLs. The Host must lock and
audit the selected SDK and its transitives in its own Composer lock. The Core
aggregate's `suggest` entries do not resolve or install packages.

This point-in-time review is not release qualification. Core publication first
requires a new immutable split package version containing these classes, fixed
candidate qualification, package-content inspection and the existing release
approval chain. Application adoption additionally requires that exact published
version to resolve from Packagist and be frozen in the application lock.

## Exact Write Set And Stop Line

This dependency decision accompanies only the low-level storage interfaces,
drivers, Core Composer suggestions and documentation registrations assigned to
the same boundary task. It adds no schema, migration, Runtime operation, HTTP
route, provider credential, aggregate SDK installation, Tag, Release, package
publication or downstream lock change.
