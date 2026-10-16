<?php

namespace Plugins\Glitter\Translate;

use App\Extension\AbstractPlugin;
use Plugins\Glitter\Translate\Listeners\TranslatedContentRepresentationListener;

class Plugin extends AbstractPlugin
{
    public function getHookListeners(): array
    {
        return [
            TranslatedContentRepresentationListener::class,
        ];
    }

    public function getMetadata(): array
    {
        return [
            'author' => 'Glitter.kr',
            'publisher' => 'Glitter.kr',
            'license' => 'MIT',
            'license_holder' => 'Glitter.kr',
            'copyright_holder' => 'Glitter.kr',
            'keywords' => ['translation', 'locale', 'sirsoft-board', 'post'],
        ];
    }

    public function getPermissions(): array
    {
        return [
            'name' => ['ko' => 'Glitter Translate', 'en' => 'Glitter Translate'],
            'description' => [
                'ko' => '게시글 번역 관리 권한',
                'en' => 'Post translation management permissions',
            ],
            'categories' => [
                [
                    'identifier' => 'translations',
                    'name' => ['ko' => '번역', 'en' => 'Translations'],
                    'description' => [
                        'ko' => '게시글 번역의 조회와 변경 권한',
                        'en' => 'Permissions to view and change post translations',
                    ],
                    'permissions' => [
                        [
                            'action' => 'read',
                            'name' => ['ko' => '번역 조회', 'en' => 'View translations'],
                            'description' => [
                                'ko' => '게시글 번역과 현재/오래된 상태를 조회할 수 있는 권한',
                                'en' => 'View post translations and their current or stale status',
                            ],
                            'type' => 'admin',
                            'roles' => ['admin'],
                        ],
                        [
                            'action' => 'write',
                            'name' => ['ko' => '번역 관리', 'en' => 'Manage translations'],
                            'description' => [
                                'ko' => '게시글 번역을 생성, 수정 및 삭제할 수 있는 권한',
                                'en' => 'Create, update, and delete post translations',
                            ],
                            'type' => 'admin',
                            'roles' => ['admin'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function getAdminMenus(): array
    {
        return [
            [
                'name' => ['ko' => '번역 관리', 'en' => 'Translations'],
                'slug' => 'glitter-translate',
                'url' => '/admin/plugins/glitter-translate',
                'icon' => 'fas fa-language',
                'order' => 45,
                'permission' => 'glitter-translate.translations.read',
            ],
        ];
    }
}
