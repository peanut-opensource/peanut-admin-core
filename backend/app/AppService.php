<?php

declare(strict_types=1);

namespace PeanutAdmin\App;

use LogicException;
use PeanutAdmin\App\authorization\DataPermissionComposition;
use PeanutAdmin\App\command\HealthCheckService;
use PeanutAdmin\App\controller\api\WorkspaceContextService;
use PeanutAdmin\App\filemedia\FileHttpService;
use PeanutAdmin\App\filemedia\LocalPrivateStorageProvider;
use PeanutAdmin\App\http\TenantModuleRuntime;
use PeanutAdmin\App\integrationsecurity\IntegrationSecurityHttpRuntime;
use PeanutAdmin\App\importexport\ImportExportHttpRuntime;
use PeanutAdmin\App\importexport\TenantMemberDirectoryProvider;
use PeanutAdmin\App\importexport\ThinkPhpFileMediaGateway;
use PeanutAdmin\App\module\OpisTenantModuleConfigValidator;
use PeanutAdmin\App\notification\NotificationHttpRuntime;
use PeanutAdmin\App\notification\ThinkPhpAttachmentResolver;
use PeanutAdmin\App\notification\ThinkPhpRecipientResolver;
use PeanutAdmin\App\ops\HostRuntimeStatusProvider;
use PeanutAdmin\App\ops\ReferenceBackupRestoreProvider;
use PeanutAdmin\App\ops\ThinkPhpMaintenanceWindowStore;
use PeanutAdmin\App\ops\ThinkPhpOpsTaskDispatcher;
use PeanutAdmin\App\ops\ThinkPhpPlatformPermissionChecker;
use PeanutAdmin\App\ops\ThinkPhpRuntimeLogProvider;
use PeanutAdmin\App\module\RuntimeModuleRegistry;
use PeanutAdmin\App\referencecode\ReferenceCodeHttpService;
use PeanutAdmin\App\setting\SettingDefinitionCatalog;
use PeanutAdmin\App\setting\SettingsHttpService;
use PeanutAdmin\App\task\TaskHttpRuntime;
use PeanutAdmin\App\task\TaskWorkerService;
use PeanutAdmin\App\task\ThinkPhpTaskAuthorizationRevalidator;
use PeanutAdmin\App\upgrade\UpgradeStatusService;
use PeanutAdmin\App\Modules\Example\Reference\Contracts\ReferenceQuery;
use PeanutAdmin\App\Modules\Example\Reference\ModuleProvider as ReferenceModuleProvider;
use PeanutAdmin\App\Modules\Example\Target\Contracts\TargetQuery;
use PeanutAdmin\App\Modules\Example\Target\ModuleProvider as TargetModuleProvider;
use PeanutAdmin\App\Modules\Example\WorkItem\Contracts\WorkItemCommands;
use PeanutAdmin\App\Modules\Example\WorkItem\Contracts\WorkItemPolicyPublication;
use PeanutAdmin\App\Modules\Example\WorkItem\Contracts\WorkItemQuery;
use PeanutAdmin\App\Modules\Example\WorkItem\ModuleProvider as WorkItemModuleProvider;
use PeanutAdmin\DataPermission\Engine\DataPermissionEngine;
use PeanutAdmin\DataPermission\Application\DataPolicyAdminService;
use PeanutAdmin\DataPermission\Application\EffectiveAccessPreviewService;
use PeanutAdmin\DataPermission\Catalog\ResourceOperationCatalog;
use PeanutAdmin\DataPermission\Catalog\ThinkPhpResourceOperationCatalog;
use PeanutAdmin\DataPermission\Policy\PolicyRepository;
use PeanutAdmin\DataPermission\Policy\ThinkPhpPolicyRepository;
use PeanutAdmin\DataPermission\Runtime\DataPermissionRuntimeRegistry;
use PeanutAdmin\FileMedia\Application\FileMediaException;
use PeanutAdmin\FileMedia\Application\FileService;
use PeanutAdmin\FileMedia\Application\UploadPolicy;
use PeanutAdmin\FileMedia\Storage\ObjectStorageProvider;
use PeanutAdmin\FileMedia\Storage\PrivateStorageAdapter;
use PeanutAdmin\FileMedia\Storage\StorageProvider;
use PeanutAdmin\IntegrationSecurity\Application\MachineIdentityService;
use PeanutAdmin\IntegrationSecurity\Application\MachineScopeCatalog;
use PeanutAdmin\IntegrationSecurity\Application\MachineScopeGrantPolicy;
use PeanutAdmin\IntegrationSecurity\Application\MachineScopeGrantResolver;
use PeanutAdmin\IntegrationSecurity\Application\SessionSecurityService;
use PeanutAdmin\IntegrationSecurity\Application\WebhookDeliveryLogService;
use PeanutAdmin\IntegrationSecurity\Application\WebhookService;
use PeanutAdmin\IntegrationSecurity\Crypto\AesGcmWebhookSecretProtector;
use PeanutAdmin\IntegrationSecurity\Persistence\IntegrationSecurityRepository;
use PeanutAdmin\IntegrationSecurity\Persistence\ThinkPhpIntegrationSecurityRepository;
use PeanutAdmin\IntegrationSecurity\Webhook\SystemHostAddressResolver;
use PeanutAdmin\IntegrationSecurity\Webhook\WebhookDestinationPolicy;
use PeanutAdmin\ImportExport\Application\ImportExportService;
use PeanutAdmin\ImportExport\Contract\DataProviderRegistry;
use PeanutAdmin\ImportExport\Execution\CsvOperationRunner;
use PeanutAdmin\ImportExport\Execution\ImportExportTaskHandler;
use PeanutAdmin\ImportExport\Execution\ImportExportTaskSubmissionProvider;
use PeanutAdmin\ImportExport\Persistence\ImportExportStore;
use PeanutAdmin\Kernel\Audit\AuditService;
use PeanutAdmin\Kernel\Auth\Persistence\ThinkPhpPlatformAuthRepository;
use PeanutAdmin\Kernel\Auth\Persistence\ThinkPhpTenantAuthRepository;
use PeanutAdmin\Kernel\Auth\PlatformAuthRepository;
use PeanutAdmin\Kernel\Auth\PlatformAuthService;
use PeanutAdmin\Kernel\Auth\SystemClock;
use PeanutAdmin\Kernel\Auth\TenantAuthService;
use PeanutAdmin\Kernel\Auth\TenantAuthRepository;
use PeanutAdmin\Kernel\Auth\TenantClientRegistry;
use PeanutAdmin\Kernel\Auth\TokenIssuer;
use PeanutAdmin\Kernel\Async\AsyncAuthorizationRevalidator;
use PeanutAdmin\Kernel\Async\TrustedEnvelopeCodec;
use PeanutAdmin\Kernel\Context\AuthorizedOperationContext;
use PeanutAdmin\Kernel\Authorization\RevisionPermissionCache;
use PeanutAdmin\Kernel\Authorization\DataPermissionAdapter;
use PeanutAdmin\Kernel\Authorization\Application\RoleAdminService;
use PeanutAdmin\Kernel\Authorization\TenantAuthorizationEvaluator;
use PeanutAdmin\Kernel\Authorization\TenantAuthorizationRepository;
use PeanutAdmin\Kernel\Authorization\ThinkPhpTenantAuthorizationRepository;
use PeanutAdmin\Kernel\Http\TenantAuthEndpoint;
use PeanutAdmin\Kernel\Http\PermissionMiddleware;
use PeanutAdmin\Kernel\Host\ExternalOperationHost;
use PeanutAdmin\Kernel\Host\ModuleAvailabilityAdapter;
use PeanutAdmin\Kernel\Host\PermissionAdapter;
use PeanutAdmin\Kernel\Host\ProblemDetailsAdapter;
use PeanutAdmin\Kernel\Host\TrustedContextAdapter;
use PeanutAdmin\Kernel\Host\TypedTargetAdapter;
use PeanutAdmin\Kernel\Idempotency\IdempotencyService;
use PeanutAdmin\Kernel\Identity\PasswordHasher;
use PeanutAdmin\Kernel\Identity\SelfService\AccountSelfService;
use PeanutAdmin\Kernel\Membership\Application\MemberAdminService;
use PeanutAdmin\Kernel\Menu\MenuCatalogRepository;
use PeanutAdmin\Kernel\Menu\ThinkPhpMenuCatalogRepository;
use PeanutAdmin\Kernel\Module\ModuleAvailabilityService;
use PeanutAdmin\Kernel\Module\ModuleRuntimeRepository;
use PeanutAdmin\Kernel\Module\Persistence\ThinkPhpModuleRuntimeRepository;
use PeanutAdmin\Kernel\Module\TenantModuleConfigurationService;
use PeanutAdmin\Kernel\Module\TenantModuleConfigValidator;
use PeanutAdmin\Kernel\Module\TenantModuleManager;
use PeanutAdmin\Kernel\Module\TenantModuleMutationRepository;
use PeanutAdmin\Kernel\Organization\Application\DepartmentAdminService;
use PeanutAdmin\Kernel\Override\ServiceOverrideRegistry;
use PeanutAdmin\Kernel\Override\ServiceOverrideSlot;
use PeanutAdmin\Kernel\Platform\Authorization\PlatformAuthorizationEvaluator;
use PeanutAdmin\Kernel\Platform\Authorization\PlatformAuthorizationRepository;
use PeanutAdmin\Kernel\Platform\Authorization\ThinkPhpPlatformAuthorizationRepository;
use PeanutAdmin\Kernel\Platform\Application\PlatformAccessAdminService;
use PeanutAdmin\Kernel\Platform\Application\PlatformTenantAdminService;
use PeanutAdmin\Kernel\Platform\Application\PlatformWorkspaceQueryService;
use PeanutAdmin\Kernel\Platform\Application\TenantOwnerAdminService;
use PeanutAdmin\Kernel\Tenancy\Application\TenantWorkspaceQueryService;
use PeanutAdmin\NotificationSms\Application\AttachmentResolver;
use PeanutAdmin\NotificationSms\Application\NotificationService;
use PeanutAdmin\NotificationSms\Application\RecipientResolver;
use PeanutAdmin\NotificationSms\Persistence\NotificationRepository;
use PeanutAdmin\NotificationSms\Persistence\NotificationStore;
use PeanutAdmin\NotificationSms\Sms\DisabledSmsProvider;
use PeanutAdmin\NotificationSms\Sms\SmsProvider;
use PeanutAdmin\NotificationSms\Sms\SmsRecipientResolver;
use PeanutAdmin\NotificationSms\Task\NotificationOutboxDispatcher;
use PeanutAdmin\NotificationSms\Task\OutboxTaskSubmissionProvider;
use PeanutAdmin\OpsConsole\Application\PlatformPermissionChecker;
use PeanutAdmin\OpsConsole\Logs\RuntimeLogProviderRegistry;
use PeanutAdmin\OpsConsole\Logs\RuntimeLogService;
use PeanutAdmin\OpsConsole\Logs\SafeLogMessageCatalog;
use PeanutAdmin\OpsConsole\Maintenance\MaintenanceReasonRegistry;
use PeanutAdmin\OpsConsole\Maintenance\MaintenanceService;
use PeanutAdmin\OpsConsole\Maintenance\MaintenanceWindowStore;
use PeanutAdmin\OpsConsole\Status\OpsStatusService;
use PeanutAdmin\OpsConsole\Status\RuntimeStatusProvider;
use PeanutAdmin\OpsConsole\Task\BackupRestoreProviderRegistry;
use PeanutAdmin\OpsConsole\Task\OpsTaskDispatcher;
use PeanutAdmin\OpsConsole\Task\OpsTaskService;
use PeanutAdmin\ReferenceCodes\Application\ReferenceCodeAdminService;
use PeanutAdmin\ReferenceCodes\Application\ReferenceCodeQuery;
use PeanutAdmin\ReferenceCodes\Persistence\ReferenceCodeStore;
use PeanutAdmin\Settings\Application\SettingAdminService;
use PeanutAdmin\Settings\Application\SettingResolver;
use PeanutAdmin\Settings\Cache\RevisionedSettingCache;
use PeanutAdmin\Settings\Cache\ThinkPhpRevisionedSettingCache;
use PeanutAdmin\Settings\Definition\SettingDefinitionSynchronizer;
use PeanutAdmin\Settings\Secret\SecretProtector;
use PeanutAdmin\Settings\Secret\SodiumSecretProtector;
use PeanutAdmin\TaskJob\Application\TaskJobService;
use PeanutAdmin\TaskJob\Persistence\TaskJobStore;
use PeanutAdmin\TaskJob\Submission\TaskSubmissionRegistry;
use PeanutAdmin\TaskJob\Submission\TrustedJobPublisher;
use RuntimeException;
use think\Service;

final class AppService extends Service
{
    public function register(): void
    {
        $overrides = require dirname(__DIR__) . '/config/service-overrides.php';
        if (!is_array($overrides)) {
            throw new RuntimeException('SERVICE_OVERRIDES_CONFIG_INVALID');
        }

        $registry = new ServiceOverrideRegistry([
            new ServiceOverrideSlot(
                'peanut.notification.service.sms-provider',
                SmsProvider::class,
                '1.0.0',
                DisabledSmsProvider::class,
            ),
        ], $overrides);
        $this->app->instance(ServiceOverrideRegistry::class, $registry);
        foreach ($registry->bindings() as $contract => $implementation) {
            $this->app->bind($contract, $implementation);
        }

        $this->app->bind(TenantAuthorizationRepository::class, ThinkPhpTenantAuthorizationRepository::class);
        $this->app->bind(PlatformAuthorizationRepository::class, ThinkPhpPlatformAuthorizationRepository::class);
        $this->app->bind(MenuCatalogRepository::class, ThinkPhpMenuCatalogRepository::class);
        $this->app->bind(ModuleRuntimeRepository::class, ThinkPhpModuleRuntimeRepository::class);
        $this->app->bind(TenantModuleMutationRepository::class, ThinkPhpModuleRuntimeRepository::class);
        $this->app->bind(TenantModuleConfigValidator::class, OpisTenantModuleConfigValidator::class);
        $this->app->bind(TenantModuleManager::class, fn(): TenantModuleManager => new TenantModuleManager(
            RuntimeModuleRegistry::compile(),
            $this->app->make(TenantModuleMutationRepository::class),
            $this->app->make(TenantModuleConfigValidator::class),
        ));
        $this->app->bind(TenantModuleConfigurationService::class, fn(): TenantModuleConfigurationService => new TenantModuleConfigurationService(
            RuntimeModuleRegistry::compile(),
            $this->app->make(TenantModuleConfigValidator::class),
            $this->app->make(ModuleRuntimeRepository::class),
            $this->app->make(AuditService::class),
        ));
        $this->app->bind(PlatformPermissionChecker::class, ThinkPhpPlatformPermissionChecker::class);
        $this->app->bind(UpgradeStatusService::class, function (): UpgradeStatusService {
            $release = getenv('UPGRADE_RELEASE_MANIFEST');
            $backup = getenv('UPGRADE_BACKUP_MANIFEST');
            $environment = getenv('APP_ENVIRONMENT');
            return new UpgradeStatusService(
                dirname(__DIR__, 2),
                is_string($release) && $release !== '' ? $release : null,
                is_string($backup) && $backup !== '' ? $backup : null,
                is_string($environment) && preg_match('/^[a-z][a-z0-9_-]{0,31}$/D', $environment) === 1
                    ? $environment
                    : null,
            );
        });
        $this->app->bind(RuntimeStatusProvider::class, fn(): RuntimeStatusProvider => new HostRuntimeStatusProvider(
            dirname(__DIR__, 2),
            $this->app->make(HealthCheckService::class),
            $this->app->make(UpgradeStatusService::class),
        ));
        $this->app->bind(MaintenanceWindowStore::class, ThinkPhpMaintenanceWindowStore::class);
        $this->app->bind(OpsTaskDispatcher::class, ThinkPhpOpsTaskDispatcher::class);
        $this->app->bind(MaintenanceService::class, fn(): MaintenanceService => new MaintenanceService(
            $this->app->make(PlatformPermissionChecker::class),
            new MaintenanceReasonRegistry(['planned-upgrade', 'database-maintenance', 'security-maintenance']),
            $this->app->make(MaintenanceWindowStore::class),
        ));
        $this->app->bind(OpsTaskService::class, fn(): OpsTaskService => new OpsTaskService(
            $this->app->make(PlatformPermissionChecker::class),
            new BackupRestoreProviderRegistry([new ReferenceBackupRestoreProvider()]),
            $this->app->make(OpsTaskDispatcher::class),
        ));
        $this->app->bind(RuntimeLogService::class, fn(): RuntimeLogService => new RuntimeLogService(
            $this->app->make(PlatformPermissionChecker::class),
            new RuntimeLogProviderRegistry([new ThinkPhpRuntimeLogProvider()]),
            new SafeLogMessageCatalog([
                'platform.ops.maintenance.scheduled' => 'A maintenance window was scheduled.',
                'platform.ops.maintenance.closed' => 'A maintenance window was closed.',
                'platform.ops.backup.submitted' => 'A backup task was submitted.',
                'platform.ops.restore.submitted' => 'A restore verification task was submitted.',
            ]),
        ));
        $this->app->bind(IntegrationSecurityRepository::class, ThinkPhpIntegrationSecurityRepository::class);
        $this->app->bind(MachineIdentityService::class, function (): MachineIdentityService {
            $config = $this->integrationSecurityConfig();
            $catalog = new MachineScopeCatalog($config['machine_scopes']);
            $resolver = new class ($config['machine_scopes']) implements MachineScopeGrantResolver {
                /** @param list<string> $scopes */
                public function __construct(private array $scopes) {}

                public function grantableScopes(AuthorizedOperationContext $context): array
                {
                    return $this->scopes;
                }
            };

            return new MachineIdentityService(
                $this->app->make(IntegrationSecurityRepository::class),
                new MachineScopeGrantPolicy($catalog, $resolver),
            );
        });
        $this->app->bind(WebhookService::class, function (): WebhookService {
            $config = $this->integrationSecurityConfig();

            return new WebhookService(
                $this->app->make(IntegrationSecurityRepository::class),
                new WebhookDestinationPolicy(new SystemHostAddressResolver()),
                new AesGcmWebhookSecretProtector($config['key_id'], $config['base64_key']),
            );
        });
        $this->app->bind(AsyncAuthorizationRevalidator::class, ThinkPhpTaskAuthorizationRevalidator::class);
        $this->app->bind(DataProviderRegistry::class, fn(): DataProviderRegistry => new DataProviderRegistry([
            new TenantMemberDirectoryProvider(),
        ]));
        $this->app->bind(TrustedEnvelopeCodec::class, function (): TrustedEnvelopeCodec {
            $config = require dirname(__DIR__) . '/config/notification-sms.php';
            $key = $config['envelope_key'] ?? null;
            if (!is_string($key) || strlen($key) < 32) {
                throw new RuntimeException('TASK_ENVELOPE_KEY_UNAVAILABLE');
            }

            return new TrustedEnvelopeCodec($key);
        });
        $this->app->bind(TrustedJobPublisher::class, fn(): TrustedJobPublisher => new TrustedJobPublisher(
            $this->app->make(TaskJobStore::class),
            new TaskSubmissionRegistry([new ImportExportTaskSubmissionProvider()]),
            $this->app->make(TrustedEnvelopeCodec::class),
        ));
        $this->app->bind(ImportExportService::class, fn(): ImportExportService => new ImportExportService(
            $this->app->make(ImportExportStore::class),
            $this->app->make(DataProviderRegistry::class),
            $this->app->make(TrustedJobPublisher::class),
            $this->app->make(TaskJobService::class),
            $this->app->make(AuditService::class),
        ));
        $this->app->bind(ImportExportTaskHandler::class, fn(): ImportExportTaskHandler => new ImportExportTaskHandler(
            new CsvOperationRunner(
                $this->app->make(ImportExportStore::class),
                $this->app->make(DataProviderRegistry::class),
                new ThinkPhpFileMediaGateway($this->app->make(FileService::class)),
                $this->app->make(AuditService::class),
            ),
        ));
        $this->app->bind(NotificationRepository::class, NotificationStore::class);
        $this->app->bind(ThinkPhpRecipientResolver::class, function (): ThinkPhpRecipientResolver {
            $config = $this->notificationConfig();

            return new ThinkPhpRecipientResolver($config['recipient_directory'], $config['recipient_digest_key']);
        });
        $this->app->bind(RecipientResolver::class, fn(): RecipientResolver => $this->app->make(ThinkPhpRecipientResolver::class));
        $this->app->bind(SmsRecipientResolver::class, fn(): SmsRecipientResolver => $this->app->make(ThinkPhpRecipientResolver::class));
        $this->app->bind(AttachmentResolver::class, ThinkPhpAttachmentResolver::class);
        $this->app->bind(NotificationOutboxDispatcher::class, fn(): NotificationOutboxDispatcher => new NotificationOutboxDispatcher(
            $this->app->make(NotificationRepository::class),
            new TrustedJobPublisher(
                $this->app->make(TaskJobStore::class),
                new TaskSubmissionRegistry([
                    new OutboxTaskSubmissionProvider('inbox'),
                    new OutboxTaskSubmissionProvider('sms'),
                ]),
                $this->app->make(TrustedEnvelopeCodec::class),
            ),
        ));
        $this->app->bind(TenantModuleRuntime::class, function (): TenantModuleRuntime {
            $modules = RuntimeModuleRegistry::compile();
            $configuration = TenantModuleRuntime::configuration();
            $permissions = new PermissionMiddleware(
                $this->app->make(TenantAuthorizationEvaluator::class),
                $this->app->make(PlatformAuthorizationEvaluator::class),
            );
            $noTargets = new DataPermissionAdapter(
                static function (): never {
                    throw new LogicException('Tenant Module operations do not accept data-query authorization.');
                },
                static function (): never {
                    throw new LogicException('Tenant Module operations do not accept typed targets.');
                },
            );
            $availability = $this->app->make(ModuleAvailabilityService::class);

            return new TenantModuleRuntime(
                new ExternalOperationHost(
                    $configuration,
                    new TrustedContextAdapter($configuration),
                    new ModuleAvailabilityAdapter($modules, $availability),
                    new PermissionAdapter($permissions),
                    new TypedTargetAdapter($noTargets),
                    $this->app->make(IdempotencyService::class),
                    $this->app->make(AuditService::class),
                    new ProblemDetailsAdapter(),
                ),
                $availability,
            );
        });
        $this->app->bind(ReferenceCodeHttpService::class, function (): ReferenceCodeHttpService {
            $modules = RuntimeModuleRegistry::compile();
            $configuration = ReferenceCodeHttpService::hostConfiguration();
            $permissions = new PermissionMiddleware(
                $this->app->make(TenantAuthorizationEvaluator::class),
                $this->app->make(PlatformAuthorizationEvaluator::class),
            );
            $unusedDataAuthorization = new DataPermissionAdapter(
                static function (): never {
                    throw new LogicException('Reference-code operations do not accept data-query authorization.');
                },
                static function (): never {
                    throw new LogicException('Reference-code operations do not accept typed targets.');
                },
            );
            $availability = $this->app->make(ModuleAvailabilityService::class);
            $host = new ExternalOperationHost(
                $configuration,
                new TrustedContextAdapter($configuration),
                new ModuleAvailabilityAdapter($modules, $availability),
                new PermissionAdapter($permissions),
                new TypedTargetAdapter($unusedDataAuthorization),
                $this->app->make(IdempotencyService::class),
                $this->app->make(AuditService::class),
                new ProblemDetailsAdapter(),
            );

            return new ReferenceCodeHttpService(
                $this->app->make(ReferenceCodeAdminService::class),
                $this->app->make(ReferenceCodeQuery::class),
                $this->app->make(ReferenceCodeStore::class),
                $modules,
                $host,
                $availability,
            );
        });
        $this->app->bind(TenantAuthRepository::class, ThinkPhpTenantAuthRepository::class);
        $this->app->bind(PlatformAuthRepository::class, ThinkPhpPlatformAuthRepository::class);
        $this->app->bind(TenantAuthService::class, function (): TenantAuthService {
            $hmacKey = getenv('AUTH_IDENTIFIER_HMAC_KEY');
            if (!is_string($hmacKey) || strlen($hmacKey) < 32) {
                throw new RuntimeException('AUTH_IDENTIFIER_HMAC_KEY must contain at least 32 bytes.');
            }
            $auth = require dirname(__DIR__) . '/config/auth.php';
            $tenant = is_array($auth['tenant'] ?? null) ? $auth['tenant'] : [];
            $clients = $tenant['clients'] ?? null;
            $default = $tenant['default_client'] ?? null;
            if (!is_array($clients) || $clients === [] || !array_is_list($clients) || !is_string($default)) {
                throw new RuntimeException('Tenant Client configuration is invalid.');
            }

            return new TenantAuthService(
                $this->app->make(TenantAuthRepository::class),
                new PasswordHasher(),
                new SystemClock(),
                new TokenIssuer(),
                $hmacKey,
                new TenantClientRegistry(array_map('strval', $clients)),
                $default,
            );
        });
        $this->app->bind(PlatformAuthService::class, function (): PlatformAuthService {
            $hmacKey = getenv('AUTH_IDENTIFIER_HMAC_KEY');
            if (!is_string($hmacKey) || strlen($hmacKey) < 32) {
                throw new RuntimeException('AUTH_IDENTIFIER_HMAC_KEY must contain at least 32 bytes.');
            }

            return new PlatformAuthService(
                $this->app->make(PlatformAuthRepository::class),
                new PasswordHasher(),
                new SystemClock(),
                new TokenIssuer(),
                $hmacKey,
            );
        });
        $this->app->bind(SecretProtector::class, static fn(): SecretProtector => SodiumSecretProtector::fromJson(
            is_string(getenv('PEANUT_SETTINGS_SECRET_KEYS')) ? (string) getenv('PEANUT_SETTINGS_SECRET_KEYS') : '',
            is_string(getenv('PEANUT_SETTINGS_ACTIVE_SECRET_KEY_ID'))
                ? (string) getenv('PEANUT_SETTINGS_ACTIVE_SECRET_KEY_ID')
                : '',
        ));
        $this->app->bind(RevisionedSettingCache::class, ThinkPhpRevisionedSettingCache::class);
        $this->app->bind(ObjectStorageProvider::class, function (): ObjectStorageProvider {
            $config = $this->fileMediaConfig();
            if ($config['provider'] !== 'local-private') {
                throw FileMediaException::storageUnavailable();
            }

            return new PrivateStorageAdapter(new LocalPrivateStorageProvider(
                $config['local_root'],
                $config['public_roots'],
            ));
        });
        $this->app->bind(StorageProvider::class, fn(): StorageProvider => $this->app->make(ObjectStorageProvider::class));
        $this->app->bind(UploadPolicy::class, function (): UploadPolicy {
            $config = $this->fileMediaConfig();

            return new UploadPolicy($config['allowed_media_types'], $config['max_bytes']);
        });
        $this->app->bind(FileHttpService::class, function (): FileHttpService {
            $config = $this->fileMediaConfig();

            return new FileHttpService(
                $this->app->make(FileService::class),
                $this->app->make(ObjectStorageProvider::class),
                $this->app->make(AuditService::class),
                $this->app->make(ModuleAvailabilityService::class),
                (string) $config['delivery_base_url'],
                (string) $config['delivery_signing_key'],
            );
        });
        $this->app->bind(DataPermissionRuntimeRegistry::class, fn(): DataPermissionRuntimeRegistry => $this->app
            ->make(DataPermissionComposition::class)
            ->runtime());
        $this->app->bind(ResourceOperationCatalog::class, ThinkPhpResourceOperationCatalog::class);
        $this->app->bind(PolicyRepository::class, ThinkPhpPolicyRepository::class);
        $this->app->bind(DataPermissionEngine::class, fn(): DataPermissionEngine => $this->app
            ->make(DataPermissionComposition::class)
            ->engine($this->app->make(DataPermissionRuntimeRegistry::class)));
        $this->app->bind(DataPolicyAdminService::class, fn(): DataPolicyAdminService => new DataPolicyAdminService(
            $this->app->make(DataPermissionRuntimeRegistry::class)->targetResolvers,
            $this->app->make(AuditService::class),
        ));
        $this->app->bind(EffectiveAccessPreviewService::class, fn(): EffectiveAccessPreviewService => new EffectiveAccessPreviewService(
            $this->app->make(TenantAuthorizationRepository::class),
            $this->app->make(ResourceOperationCatalog::class),
            $this->app->make(PolicyRepository::class),
            $this->app->make(AuditService::class),
        ));
        $this->app->bind(TargetQuery::class, static fn(): TargetQuery => (new TargetModuleProvider())->targetQuery());
        $this->app->bind(ReferenceQuery::class, fn(): ReferenceQuery => (new ReferenceModuleProvider())->referenceQuery(
            $this->app->make(DataPermissionEngine::class),
        ));
        $this->app->bind(WorkItemQuery::class, fn(): WorkItemQuery => (new WorkItemModuleProvider())->workItemQuery(
            $this->app->make(DataPermissionEngine::class),
            $this->app->make(TargetQuery::class),
        ));
        $this->app->bind(WorkItemCommands::class, fn(): WorkItemCommands => (new WorkItemModuleProvider())->workItemCommands(
            $this->app->make(DataPermissionEngine::class),
            $this->app->make(AuditService::class),
            $this->app->make(MemberAdminService::class),
        ));
        $this->app->bind(
            WorkItemPolicyPublication::class,
            fn(): WorkItemPolicyPublication => (new WorkItemModuleProvider())->workItemPolicyPublication(
                $this->app->make(DataPermissionEngine::class),
                $this->app->make(AuditService::class),
            ),
        );
    }

    /** @return array{key_id:string,base64_key:string,machine_scopes:list<string>} */
    private function integrationSecurityConfig(): array
    {
        $config = require dirname(__DIR__) . '/config/integration-security.php';
        $scopes = is_array($config) && is_array($config['machine_scopes'] ?? null)
            ? $config['machine_scopes']
            : null;
        if (!is_array($config) || !is_string($config['key_id'] ?? null)
            || !is_string($config['base64_key'] ?? null) || !is_array($scopes) || !array_is_list($scopes)) {
            throw new RuntimeException('INTEGRATION_SECURITY_CONFIG_INVALID');
        }
        foreach ($scopes as $scope) {
            if (!is_string($scope)) {
                throw new RuntimeException('INTEGRATION_SECURITY_CONFIG_INVALID');
            }
        }

        return ['key_id' => $config['key_id'], 'base64_key' => $config['base64_key'], 'machine_scopes' => $scopes];
    }

    /** @return array{recipient_digest_key:string,recipient_directory:array<string,mixed>} */
    private function notificationConfig(): array
    {
        $config = require dirname(__DIR__) . '/config/notification-sms.php';
        if (!is_array($config) || !is_string($config['recipient_digest_key'] ?? null)
            || !is_array($config['recipient_directory'] ?? null)) {
            throw new RuntimeException('NOTIFICATION_CONFIG_INVALID');
        }

        return [
            'recipient_digest_key' => $config['recipient_digest_key'],
            'recipient_directory' => $config['recipient_directory'],
        ];
    }

    /**
     * @return array{
     *     provider:string,
     *     delivery_base_url:string,
     *     delivery_signing_key:string,
     *     local_root:string,
     *     public_roots:list<string>,
     *     max_bytes:int,
     *     allowed_media_types:list<string>
     * }
     */
    private function fileMediaConfig(): array
    {
        $config = require dirname(__DIR__) . '/config/file-media.php';
        $publicRoots = is_array($config) ? ($config['public_roots'] ?? null) : null;
        $allowedMediaTypes = is_array($config) ? ($config['allowed_media_types'] ?? null) : null;
        if (!is_array($config)
            || !is_string($config['provider'] ?? null)
            || !is_string($config['delivery_base_url'] ?? null)
            || !is_string($config['delivery_signing_key'] ?? null)
            || !is_string($config['local_root'] ?? null)
            || !is_array($publicRoots)
            || !array_is_list($publicRoots)
            || !is_array($allowedMediaTypes)
            || !array_is_list($allowedMediaTypes)
            || !is_int($config['max_bytes'] ?? null)) {
            throw new RuntimeException('FILE_MEDIA_CONFIG_INVALID');
        }
        foreach ([...$publicRoots, ...$allowedMediaTypes] as $value) {
            if (!is_string($value) || $value === '') {
                throw new RuntimeException('FILE_MEDIA_CONFIG_INVALID');
            }
        }

        return [
            'provider' => $config['provider'],
            'delivery_base_url' => $config['delivery_base_url'],
            'delivery_signing_key' => $config['delivery_signing_key'],
            'local_root' => $config['local_root'],
            'public_roots' => $publicRoots,
            'max_bytes' => $config['max_bytes'],
            'allowed_media_types' => $allowedMediaTypes,
        ];
    }
}
