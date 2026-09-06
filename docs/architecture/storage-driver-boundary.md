# Storage Driver Boundary

> Status: current. The product-neutral Core Runtime boundary is implemented in the current worktree; package publication and downstream adoption still require their own immutable evidence.

## Ownership

`peanut-admin-core` owns product-neutral storage mechanisms and explicit technical state. Peanut Admin Application owns business rules, product data, UI, host composition, provider credentials, authorization, object provenance and lifecycle. Each table and state machine has one owner. Core does not reference the Application repository, its schema, or its existing FileMedia lifecycle.

The Application repository remains the only manual source for product code. Reusable business-neutral packages live in Core. Standalone and Multi-tenant editions are generated deterministically from the same frozen Application source; no independent manual single-tenant source repository is maintained.

## Driver contract

The low-level `FileMedia\\Storage\\StorageDriver` contract has four operations:

```text
put(objectKey, sourcePath)
delete(objectKey)
downloadTo(objectKey, destinationPath)
localPath(objectKey)
```

`StorageObjectKey` validates only a technical object key. Tenant identity, purpose, authorization, object ledger, compensation, account-space routing, credential decryption and observed health remain Application concerns.

Core provides four driver implementations: local, Aliyun, Qcloud and Qiniu. Concrete Aliyun/Qcloud/Qiniu SDK instances are assembled by the Application composition root and injected using concrete SDK types. The Core aggregate does not force those three SDK dependencies. An accepted DDR may expose the exact optional SDK requirement through Composer `suggest`; the consuming Host must explicitly require and lock that SDK before constructing the provider, and users without it cannot enable the provider. Flysystem and a new split package are out of scope.

Qiniu uses a narrow `StorageHttpTransport` contract. The Application adapter maps the existing `OutboundHttpTransport` timeout, retrySafe, sink and multipart behavior. It does not copy the complete HTTP DTO set or create a second transport stack.

## Runtime and data boundary

`ObservedStorageDriver`, account-space routing, credential decryption, purpose, authorization, object ledger and compensation remain in the Application. Core does not enable the other FileMedia lifecycle or schema and does not alter tables. HTTP whole-file download is a later proposal and is excluded from this extraction queue.

The former PB04 FileMedia Host contract in the Application repository remains historical evidence. It is not rewritten to manufacture migration proof; this page is the current planned boundary for the storage extraction. Runtime adoption requires the exact Core commit, immutable Composer split version and lock, Application wiring, focused verification and fixed evidence.

## Comment rule

New or materially changed classes and methods use concise responsibility comments. Complex methods state tenant/authorization prerequisites, side effects, exceptions and stream or temporary-file ownership. Clear inherited interface documentation may carry the contract without repeating native types. Standard CRUD, accessors and constructor-only injection may omit method comments, but class responsibility comments remain required. Tenant permissions, transactions, idempotency, soft deletion and external side effects are never exempt. Explain variables only when their unit, invariant or security reason is not obvious.
