# External Operation Host Example

This executable example registers five operations for the fictional
`fixture.record` Module. It demonstrates host-owned namespace, API prefix,
OpenAPI and generated-artifact paths while using the reusable Kernel Host kit.

The fixture proves only list, detail, create, update, and status composition.
It is not a generic repository or CRUD engine and it is not part of the
reference host.

Run the contract with:

```bash
php vendor/bin/phpunit examples/external-host/ExampleExternalHostContractTest.php
```
