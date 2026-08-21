<?php

declare(strict_types=1);

use PeanutAdmin\App\controller\api\v1\DataAuthorizationController;
use PeanutAdmin\App\controller\api\v1\DepartmentController;
use PeanutAdmin\App\controller\api\v1\MemberController;
use PeanutAdmin\App\controller\api\v1\RoleController;

return [
    'GET /api/v1/members' => [MemberController::class, 'index', 'core.member.read'],
    'POST /api/v1/members' => [MemberController::class, 'create', 'core.member.create'],
    'GET /api/v1/members/{member_id}' => [MemberController::class, 'show', 'core.member.read'],
    'GET /api/v1/members/{member_id}/effective-access' => [
        DataAuthorizationController::class,
        'effectiveAccess',
        'core.member.effective-access.read',
    ],
    'PATCH /api/v1/members/{member_id}' => [MemberController::class, 'update', 'core.member.update'],
    'PUT /api/v1/members/{member_id}/roles' => [MemberController::class, 'replaceRoles', 'core.member.role.assign'],
    'POST /api/v1/members/{member_id}/suspend' => [MemberController::class, 'suspend', 'core.member.suspend'],
    'POST /api/v1/members/{member_id}/activate' => [MemberController::class, 'activate', 'core.member.activate'],
    'POST /api/v1/members/{member_id}/leave' => [MemberController::class, 'leave', 'core.member.leave'],
    'GET /api/v1/departments' => [DepartmentController::class, 'index', 'core.department.read'],
    'POST /api/v1/departments' => [DepartmentController::class, 'create', 'core.department.create'],
    'GET /api/v1/departments/{department_id}' => [DepartmentController::class, 'show', 'core.department.read'],
    'PATCH /api/v1/departments/{department_id}' => [DepartmentController::class, 'update', 'core.department.update'],
    'POST /api/v1/departments/{department_id}/move' => [DepartmentController::class, 'move', 'core.department.move'],
    'POST /api/v1/departments/{department_id}/archive' => [DepartmentController::class, 'archive', 'core.department.archive'],
    'GET /api/v1/roles' => [RoleController::class, 'index', 'core.role.read'],
    'POST /api/v1/roles' => [RoleController::class, 'create', 'core.role.create'],
    'GET /api/v1/roles/{role_id}' => [RoleController::class, 'show', 'core.role.read'],
    'PATCH /api/v1/roles/{role_id}' => [RoleController::class, 'update', 'core.role.update'],
    'POST /api/v1/roles/{role_id}/archive' => [RoleController::class, 'archive', 'core.role.archive'],
    'PUT /api/v1/roles/{role_id}/permissions' => [RoleController::class, 'replacePermissions', 'core.role.permission.assign'],
];
