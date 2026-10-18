<?php

namespace Modules\Glitter\FormBuilder;

use App\Extension\AbstractModule;

/**
 * Glitter Form Builder module entry point.
 *
 * Phase 2 declares only the Form/FormVersion admin domain surface.
 */
class Module extends AbstractModule
{
    public function getPermissions(): array
    {
        return [
            'name' => [
                'ko' => '폼 관리',
                'en' => 'Glitter Form Builder',
            ],
            'description' => ['ko' => '폼 관리 권한', 'en' => 'Form management permissions'],
            'categories' => [[
                'identifier' => 'forms',
                'resource_route_key' => 'form',
                'owner_key' => 'created_by',
                'name' => ['ko' => '폼 관리', 'en' => 'Form Management'],
                'description' => ['ko' => '폼 정의와 버전 관리 권한', 'en' => 'Form definition and version permissions'],
                'permissions' => [
                    $this->permission('read', '폼 조회', 'View Forms', ['admin', 'manager']),
                    $this->permission('create', '폼 생성', 'Create Forms', ['admin', 'manager']),
                    $this->permission('update', '폼 수정', 'Update Forms', ['admin', 'manager']),
                    $this->permission('delete', '폼 삭제', 'Delete Forms', ['admin']),
                    $this->permission('publish', '폼 게시', 'Publish Forms', ['admin', 'manager']),
                ],
            ], [
                'identifier' => 'submissions',
                'resource_route_key' => 'submission',
                'name' => [
                    'ko' => '제출',
                    'en' => 'Submissions',
                ],
                'description' => ['ko' => '제출 조회 권한', 'en' => 'Submission read permissions'],
                'permissions' => [
                    $this->permission('read', '제출 조회', 'View Submissions', ['admin', 'manager']),
                ],
            ]],
        ];
    }

    public function getAdminMenus(): array
    {
        return [[
            'name' => [
                'ko' => '반짝이 폼 관리',
                'en' => 'Glitter Form Management',
            ],
            'slug' => 'glitter-form_builder',
            'url' => null,
            'icon' => 'fas fa-list-alt',
            'order' => 35,
            'children' => [[
                'name' => ['ko' => '폼', 'en' => 'Forms'],
                'slug' => 'glitter-form_builder-forms',
                'url' => '/admin/form-builder/forms',
                'icon' => 'fas fa-list',
                'order' => 1,
                'permission' => 'glitter-form_builder.forms.read',
            ], [
                'name' => [
                    'ko' => '제출',
                    'en' => 'Submissions',
                ],
                'slug' => 'glitter-form_builder-submissions',
                'url' => '/admin/form-builder/submissions',
                'icon' => 'fas fa-inbox',
                'order' => 2,
                'permission' => 'glitter-form_builder.submissions.read',
            ]],
        ]];
    }

    private function permission(string $action, string $ko, string $en, array $roles): array
    {
        return [
            'action' => $action,
            'name' => ['ko' => $ko, 'en' => $en],
            'description' => ['ko' => $ko, 'en' => $en],
            'type' => 'admin',
            'roles' => $roles,
        ];
    }
}
