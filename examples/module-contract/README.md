# Example Module Contract

This executable fixture proves the one-way dependency graph:

```text
example.target -> example.reference -> example.work-item
       \------------------------------------^
```

It uses only fictional Project, Queue, ReferenceItem, and WorkItem records. Run it with:

```bash
PEANUT_INTEGRATION=1 php vendor/bin/phpunit examples/module-contract/ExampleModuleContractTest.php
```
