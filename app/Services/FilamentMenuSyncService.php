<?php

namespace App\Services;

use App\Models\Trmenu;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class FilamentMenuSyncService
{
    /**
     *
     * @return int jumlah menu baru yang dibuat
     */
    public function sync(bool $force = false): int
    {
        if (! $force) {
            $last = Cache::get('menus:last_sync_at');
            if ($last && now()->diffInSeconds($last) < 120) {
                return 0;
            }
        }

        Cache::put('menus:last_sync_at', now(), 600);

        $inserted = 0;

        foreach ($this->discoverNavigationClasses() as $class) {
            try {
                if (is_subclass_of($class, Resource::class)) {
                    $inserted += $this->syncFromResource($class);
                    continue;
                }

                if (is_subclass_of($class, Page::class)) {
                    $inserted += $this->syncFromPage($class);
                    continue;
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        return $inserted;
    }

    /**
     * @return array<int, class-string>
     */
    private function discoverNavigationClasses(): array
    {
        $classes = [];

        $roots = [
            app_path('Filament/Resources'),
            app_path('Filament/Pages'),
        ];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            foreach (File::allFiles($root) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $class = $this->classFromAppPath($file->getPathname());
                if (! $class) {
                    continue;
                }

                if (class_exists($class)) {
                    $classes[] = $class;
                }
            }
        }

        return array_values(array_unique($classes));
    }

    private function classFromAppPath(string $absolutePath): ?string
    {
        $app = app_path();

        if (! Str::startsWith($absolutePath, $app)) {
            return null;
        }

        $relative = Str::after($absolutePath, $app . DIRECTORY_SEPARATOR);
        $relative = str_replace(['/', '\\'], '\\', $relative);
        $relative = Str::replaceLast('.php', '', $relative);

        return 'App\\' . $relative;
    }

    /**
     * @param class-string<Resource> $resourceClass
     */
    private function syncFromResource(string $resourceClass): int
    {
        if (method_exists($resourceClass, 'shouldRegisterNavigation') && ! $resourceClass::shouldRegisterNavigation()) {
            return 0;
        }

        $slug = method_exists($resourceClass, 'getSlug')
            ? (string) $resourceClass::getSlug()
            : $this->fallbackSlugFromClass($resourceClass, 'Resource');

        $code = $this->toMenuCode($slug);

        $label = method_exists($resourceClass, 'getNavigationLabel')
            ? (string) $resourceClass::getNavigationLabel()
            : $this->fallbackLabelFromCode($code);

        return $this->upsertMenu($code, $label);
    }

    /**
     * @param class-string<Page> $pageClass
     */
    private function syncFromPage(string $pageClass): int
    {
        if (method_exists($pageClass, 'shouldRegisterNavigation') && ! $pageClass::shouldRegisterNavigation()) {
            return 0;
        }

        $slug = method_exists($pageClass, 'getSlug')
            ? (string) $pageClass::getSlug()
            : $this->fallbackSlugFromClass($pageClass, '');

        $code = $this->toMenuCode($slug);

        $label = method_exists($pageClass, 'getNavigationLabel')
            ? (string) $pageClass::getNavigationLabel()
            : $this->fallbackLabelFromCode($code);

        return $this->upsertMenu($code, $label);
    }

    private function fallbackSlugFromClass(string $class, string $suffixToStrip): string
    {
        $base = class_basename($class);

        if ($suffixToStrip !== '' && Str::endsWith($base, $suffixToStrip)) {
            $base = Str::replaceLast($suffixToStrip, '', $base);
        }

        return Str::kebab(Str::pluralStudly($base));
    }

    private function toMenuCode(string $slug): string
    {
        $slug = trim($slug);
        $slug = $slug === '' ? 'menu' : $slug;

        return (string) Str::of($slug)
            ->lower()
            ->replace('-', '_')
            ->replace(' ', '_');
    }

    private function fallbackLabelFromCode(string $code): string
    {
        return Str::of($code)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    private function upsertMenu(string $code, string $label): int
    {
        $code = trim($code);
        $label = trim($label) !== '' ? trim($label) : $code;

        $menu = Trmenu::where('c_menu', $code)->first();

        if (! $menu) {
            Trmenu::create([
                'c_menu'   => $code,
                'n_menu'   => $label,
                'e_menu'   => null,
                'f_active' => true,
            ]);

            return 1;
        }

        $dirty = false;

        if (blank($menu->n_menu)) {
            $menu->n_menu = $label;
            $dirty = true;
        }

        if (is_null($menu->f_active)) {
            $menu->f_active = true;
            $dirty = true;
        }

        if ($dirty) {
            $menu->save();
        }

        return 0;
    }
}
