<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class HelpCenterController extends Controller
{
    public function index(): \Illuminate\Contracts\View\View
    {
        return view('help.index');
    }

    public function document(?string $path = null): Response
    {
        $requestedPath = trim($path ?? '', '/');
        $normalizedPath = $requestedPath === '' ? 'introduction.md' : $requestedPath;

        if (
            Str::contains($normalizedPath, '..')
            || !Str::endsWith($normalizedPath, '.md')
            || !preg_match('/^[A-Za-z0-9_\\-\\/\\.]+$/', $normalizedPath)
        ) {
            abort(404);
        }

        $docsRoot = realpath(resource_path('docs'));
        if ($docsRoot === false) {
            abort(404);
        }

        $absolutePath = realpath($docsRoot.DIRECTORY_SEPARATOR.$normalizedPath);
        if (
            $absolutePath === false
            || !Str::startsWith($absolutePath, $docsRoot.DIRECTORY_SEPARATOR)
            || !is_file($absolutePath)
        ) {
            abort(404);
        }

        $content = file_get_contents($absolutePath);
        if ($content === false) {
            abort(404);
        }

        if (Str::endsWith($normalizedPath, '_sidebar.md')) {
            $content = $this->filterSidebarByAccess($content);
        } else {
            $metadata = $this->extractVisibilityMetadata($content);

            if (!$this->canViewWithMetadata($metadata)) {
                abort(404);
            }

            if (!empty($metadata['comment'])) {
                $content = str_replace($metadata['comment'], '', $content);
                $content = ltrim($content, "\r\n");
            }
        }

        $content = $this->absolutizeApplicationLinks($content);

        return response($content, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function filterSidebarByAccess(string $content): string
    {
        $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];
        $visibleLines = [];

        foreach ($lines as $line) {
            $metadata = $this->extractVisibilityMetadata($line);
            if (!$this->canViewWithMetadata($metadata)) {
                continue;
            }

            if (!empty($metadata['comment'])) {
                $line = trim(str_replace($metadata['comment'], '', $line));
            }

            $visibleLines[] = $line;
        }

        return implode("\n", $visibleLines);
    }

    /**
     * @return array{guards: array<int, string>, permissions: array<int, string>, any_permissions: array<int, string>, comment: string|null}
     */
    private function extractVisibilityMetadata(string $content): array
    {
        $metadata = [
            'guards' => [],
            'permissions' => [],
            'any_permissions' => [],
            'comment' => null,
        ];

        if (!preg_match('/<!--\s*([^>]*)\s*-->/', $content, $matches)) {
            return $metadata;
        }

        $comment = $matches[1] ?? '';
        if (
            !Str::contains($comment, '@guards:')
            && !Str::contains($comment, '@permissions:')
            && !Str::contains($comment, '@any-permissions:')
        ) {
            return $metadata;
        }

        $metadata['comment'] = $matches[0];

        if (preg_match('/@guards:([A-Za-z0-9_,\-\s]+)/', $comment, $guardMatches)) {
            $metadata['guards'] = $this->splitCsv($guardMatches[1]);
        }

        if (preg_match('/@permissions:([A-Za-z0-9._,\-\s]+)/', $comment, $permissionMatches)) {
            $metadata['permissions'] = $this->splitCsv($permissionMatches[1]);
        }

        if (preg_match('/@any-permissions:([A-Za-z0-9._,\-\s]+)/', $comment, $anyPermissionMatches)) {
            $metadata['any_permissions'] = $this->splitCsv($anyPermissionMatches[1]);
        }

        return $metadata;
    }

    /**
     * @param array{guards: array<int, string>, permissions: array<int, string>, any_permissions: array<int, string>, comment: string|null} $metadata
     */
    private function canViewWithMetadata(array $metadata): bool
    {
        $hasVisibilityRules = !empty($metadata['guards']) || !empty($metadata['permissions']) || !empty($metadata['any_permissions']);
        if (!$hasVisibilityRules) {
            return true;
        }

        $context = $this->currentAuthContext();
        $guard = $context['guard'];
        $user = $context['user'];

        if ($guard === null || $user === null) {
            return false;
        }

        if (!empty($metadata['guards']) && !in_array($guard, $metadata['guards'], true)) {
            return false;
        }

        foreach ($metadata['permissions'] as $permission) {
            if (!$user->can($permission)) {
                return false;
            }
        }

        if (!empty($metadata['any_permissions'])) {
            $hasAnyPermission = false;
            foreach ($metadata['any_permissions'] as $permission) {
                if ($user->can($permission)) {
                    $hasAnyPermission = true;
                    break;
                }
            }

            if (!$hasAnyPermission) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{guard: string|null, user: Authenticatable|null}
     */
    private function currentAuthContext(): array
    {
        if (auth('admin')->check()) {
            return [
                'guard' => 'admin',
                'user' => auth('admin')->user(),
            ];
        }

        if (auth('customer')->check()) {
            return [
                'guard' => 'customer',
                'user' => auth('customer')->user(),
            ];
        }

        return [
            'guard' => null,
            'user' => null,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function splitCsv(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }

    private function absolutizeApplicationLinks(string $content): string
    {
        $baseUrl = request()->getSchemeAndHttpHost();

        return preg_replace_callback('/\]\((\/[^)\s]+)\)/', function (array $matches) use ($baseUrl) {
            $path = $matches[1];

            // Ignore protocol-relative links (e.g. //cdn.example.com)
            if (Str::startsWith($path, '//')) {
                return $matches[0];
            }

            return ']('.$baseUrl.$path.')';
        }, $content) ?? $content;
    }
}
