<?php

namespace App\Http\Controllers;

use App\Services\Unzip;
use Cache;
use Composer\CaBundle\CaBundle;
use Composer\Semver\Comparator;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Contracts\Console\Kernel as Artisan;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Symfony\Component\Finder\SplFileInfo;

class UpdateController extends Controller
{
    const SPEC = 2;

    public function showUpdatePage(Client $client)
    {
        $info = $this->getUpdateInfo($client);
        $canUpdate = $this->canUpdate(Arr::get($info, 'info'));

        return view('admin.update', [
            'info' => [
                'latest' => Arr::get($info, 'info.latest'),
                'current' => config('app.version'),
            ],
            'error' => Arr::get($info, 'error', $canUpdate['reason']),
            'can_update' => $canUpdate['can'],
        ]);
    }

    public function download(Unzip $unzip, Filesystem $filesystem, Client $client)
    {
        $info = $this->getUpdateInfo($client);
        if (!$info['ok'] || !$this->canUpdate($info['info'])['can']) {
            return json(trans('admin.update.info.up-to-date'), 1);
        }

        $info = $info['info'];
        $path = tempnam(sys_get_temp_dir(), 'bs');
        try {
            $client->get($info['url'], [
                'sink' => $path,
                'verify' => CaBundle::getSystemCaRootBundlePath(),
            ]);
            $unzip->extract($path, base_path());

            // Delete options cache. This allows us to update the version.
            $filesystem->delete(storage_path('options.php'));

            return json(trans('admin.update.complete'), 0);
        } catch (Exception $e) {
            report($e);

            return json(trans('admin.download.errors.download', ['error' => $e->getMessage()]), 1);
        }
    }

    public function update(Filesystem $filesystem, Artisan $artisan)
    {
        collect($filesystem->files(database_path('update_scripts')))
            ->filter(function (SplFileInfo $file) {
                $name = $file->getFilenameWithoutExtension();

                return preg_match('/^\d+\.\d+\.\d+$/', $name) > 0
                    && Comparator::greaterThanOrEqualTo($name, option('version'));
            })
            ->each(function (SplFileInfo $file) use ($filesystem) {
                $filesystem->getRequire($file->getPathname());
            });

        option(['version' => config('app.version')]);
        $artisan->call('migrate', ['--force' => true]);
        $artisan->call('view:clear');
        $filesystem->put(storage_path('install.lock'), '');
        Cache::flush();

        return view('setup.updates.success');
    }

    protected function getUpdateInfo(Client $client)
    {
        try {
            $response = $client->request('GET', config('app.update_source'), [
                'verify' => CaBundle::getSystemCaRootBundlePath(),
            ]);
            $info = json_decode($response->getBody(), true);

            if (Arr::get($info, 'spec') === self::SPEC) {
                return ['ok' => true, 'info' => $info];
            } else {
                return ['ok' => false, 'error' => trans('admin.update.errors.spec')];
            }
        } catch (RequestException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    protected function canUpdate($info = [])
    {
        $php = Arr::get($info, 'php');
        preg_match('/(\d+\.\d+\.\d+)/', PHP_VERSION, $matches);
        $version = $matches[1];
        if (Comparator::lessThan($version, $php)) {
            return [
                'can' => false,
                'reason' => trans('admin.update.errors.php', ['version' => $php]),
            ];
        }

        $can = Comparator::greaterThan(Arr::get($info, 'latest'), config('app.version'));

        return ['can' => $can, 'reason' => ''];
    }
}
