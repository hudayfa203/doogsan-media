<?php

namespace App\Support;

/**
 * Canonical permission catalogue and default role grants.
 * Roles and grants are editable in Admin → Users & Roles; this class
 * only seeds them and documents what each key controls.
 */
class Permissions
{
    public const CATALOGUE = [
        'Editorial' => [
            'articles.view' => 'View newsroom articles',
            'articles.create' => 'Create articles',
            'articles.edit_own' => 'Edit own articles',
            'articles.edit_any' => 'Edit any article',
            'articles.submit' => 'Submit for review',
            'articles.review' => 'Review & approve (editing desk)',
            'articles.publish' => 'Publish, schedule & unpublish',
            'articles.archive' => 'Archive articles',
            'articles.delete' => 'Delete articles',
            'translations.manage' => 'Manage translations & approve machine translation',
            'corrections.manage' => 'Issue corrections & rollbacks',
        ],
        'Live & Broadcast' => [
            'breaking.manage' => 'Breaking news alerts',
            'live.manage' => 'Live coverage events',
            'video.manage' => 'Video, podcasts & Live TV',
        ],
        'Site' => [
            'homepage.manage' => 'Homepage layout',
            'menus.manage' => 'Navigation menus',
            'pages.manage' => 'Corporate & policy pages',
            'media.manage' => 'Media library',
            'authors.manage' => 'Journalist profiles',
            'taxonomy.manage' => 'Sections & tags',
            'seo.manage' => 'SEO & redirects',
            'ads.manage' => 'Advertising',
        ],
        'Audience' => [
            'analytics.view' => 'Analytics & reports',
            'inbox.manage' => 'Contact messages & news tips',
            'newsletter.manage' => 'Newsletter & subscribers',
        ],
        'Administration' => [
            'users.manage' => 'Users, roles & permissions',
            'settings.manage' => 'Site settings & branding',
            'audit.view' => 'Security & audit logs',
            'system.view' => 'System health',
            'backups.manage' => 'Backups',
        ],
    ];

    public const ROLES = [
        'super-admin' => ['Super Administrator', 'Full platform access.', true, ['*']],
        'editor-in-chief' => ['Editor-in-Chief', 'Owns editorial output across both editions.', true, [
            'articles.*', 'translations.manage', 'corrections.manage', 'breaking.manage', 'live.manage', 'video.manage',
            'homepage.manage', 'menus.manage', 'pages.manage', 'media.manage', 'authors.manage', 'taxonomy.manage',
            'seo.manage', 'analytics.view', 'inbox.manage', 'newsletter.manage', 'audit.view',
        ]],
        'managing-editor' => ['Managing Editor', 'Runs the daily desk and homepage.', true, [
            'articles.*', 'translations.manage', 'corrections.manage', 'breaking.manage', 'live.manage',
            'homepage.manage', 'media.manage', 'authors.manage', 'taxonomy.manage', 'analytics.view', 'inbox.manage',
        ]],
        'news-editor' => ['News Editor', 'Edits, approves and publishes desk copy.', true, [
            'articles.view', 'articles.create', 'articles.edit_own', 'articles.edit_any', 'articles.submit',
            'articles.review', 'articles.publish', 'translations.manage', 'breaking.manage', 'live.manage', 'media.manage',
        ]],
        'journalist' => ['Journalist / Reporter', 'Writes and submits stories; posts to assigned live coverage.', false, [
            'articles.view', 'articles.create', 'articles.edit_own', 'articles.submit', 'media.manage', 'live.manage',
        ]],
        'translator' => ['Translator', 'Prepares English ⇄ Somali versions.', false, [
            'articles.view', 'articles.edit_any', 'articles.submit', 'translations.manage', 'media.manage',
        ]],
        'video-producer' => ['Video Producer', 'Manages video, podcasts and the Live TV studio.', false, [
            'articles.view', 'video.manage', 'media.manage',
        ]],
        'social-media-manager' => ['Social Media Manager', 'Breaking alerts, sharing and newsletters.', false, [
            'articles.view', 'breaking.manage', 'newsletter.manage', 'analytics.view',
        ]],
        'seo-manager' => ['SEO Manager', 'Metadata, redirects and search performance.', false, [
            'articles.view', 'seo.manage', 'analytics.view', 'taxonomy.manage',
        ]],
        'advertising-manager' => ['Advertising Manager', 'Ad inventory and campaigns.', false, [
            'ads.manage', 'analytics.view', 'media.manage',
        ]],
        'analyst' => ['Analyst', 'Read-only analytics.', false, [
            'analytics.view', 'articles.view',
        ]],
    ];

    public static function all(): array
    {
        return collect(self::CATALOGUE)->flatMap(fn ($items) => $items)->all();
    }

    /** Expand wildcard grants like "articles.*". */
    public static function expand(array $grants): array
    {
        $all = array_keys(self::all());
        if (in_array('*', $grants, true)) {
            return $all;
        }

        return collect($grants)->flatMap(function ($g) use ($all) {
            if (str_ends_with($g, '.*')) {
                $prefix = substr($g, 0, -1);

                return array_filter($all, fn ($k) => str_starts_with($k, $prefix));
            }

            return [$g];
        })->unique()->values()->all();
    }
}
