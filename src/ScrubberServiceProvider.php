<?php

namespace YorCreative\Scrubber;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use YorCreative\Scrubber\Clients\GitLabClient;
use YorCreative\Scrubber\Repositories\RegexRepository;
use YorCreative\Scrubber\Strategies\ContentProcessingStrategy\ContentProcessingStrategy;
use YorCreative\Scrubber\Strategies\ContentProcessingStrategy\Handlers\ArrayContentHandler;
use YorCreative\Scrubber\Strategies\ContentProcessingStrategy\Handlers\LogRecordContentHandler;
use YorCreative\Scrubber\Strategies\ContentProcessingStrategy\Handlers\StringContentHandler;
use YorCreative\Scrubber\Strategies\RegexLoader\Loaders\ConfigLoader;
use YorCreative\Scrubber\Strategies\RegexLoader\Loaders\SecretLoader;
use YorCreative\Scrubber\Strategies\RegexLoader\Loaders\SpecificRegex;
use YorCreative\Scrubber\Strategies\RegexLoader\Loaders\WildcardRegex;
use YorCreative\Scrubber\Strategies\RegexLoader\RegexLoaderStrategy;
use YorCreative\Scrubber\Strategies\TapLoader\Loaders\MultipleChannel;
use YorCreative\Scrubber\Strategies\TapLoader\Loaders\SpecificChannel;
use YorCreative\Scrubber\Strategies\TapLoader\Loaders\WildCardChannel;
use YorCreative\Scrubber\Strategies\TapLoader\TapLoaderStrategy;

class ScrubberServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(dirname(__DIR__, 1).'/config/scrubber.php', 'scrubber');

        $this->commands(
            'YorCreative\\Scrubber\\Commands\\MakeRegexClass'
        );

        $this->publishes([
            dirname(__DIR__, 1).'/config' => base_path('config'),
        ]);
    }

    public function boot()
    {
        if (Config::get('scrubber.secret_manager.enabled')
            && Config::get('scrubber.secret_manager.providers.gitlab.enabled')
        ) {
            $this->app->singleton(GitlabClient::class, function () {
                return new GitLabClient(new Client([
                    'base_uri' => Config::get('scrubber.secret_manager.providers.gitlab.host'),
                    'headers' => [
                        'accept' => 'application/json',
                        'content_type' => 'application/json',
                        'authorization' => 'bearer '.Config::get('scrubber.secret_manager.providers.gitlab.token'),
                    ],
                ]));
            });
        }

        $this->app->singleton(RegexLoaderStrategy::class, function () {
            $regexLoaderStrategy = new RegexLoaderStrategy;
            $regexLoaderStrategy->setLoader(new WildcardRegex);
            $regexLoaderStrategy->setLoader(new SpecificRegex);
            $regexLoaderStrategy->setLoader(new SecretLoader);
            $regexLoaderStrategy->setLoader(new ConfigLoader);

            return $regexLoaderStrategy;
        });

        $this->app->singleton(TapLoaderStrategy::class, function () {
            $tapLoaderStrategy = new TapLoaderStrategy;
            $tapLoaderStrategy->setLoader(new WildCardChannel);
            $tapLoaderStrategy->setLoader(new SpecificChannel);
            $tapLoaderStrategy->setLoader(new MultipleChannel);

            return $tapLoaderStrategy;
        });

        $this->app->make(TapLoaderStrategy::class)->load($this->app->make('config'));

        $this->app->scoped(RegexRepository::class, function ($app) {
            return new RegexRepository($app->make(RegexLoaderStrategy::class)->load());
        });

        $this->app->singleton(ContentProcessingStrategy::class, function () {
            $contentProcessingStrategy = new ContentProcessingStrategy;

            $contentProcessingStrategy->setHandler(new StringContentHandler);
            $contentProcessingStrategy->setHandler(new ArrayContentHandler);
            $contentProcessingStrategy->setHandler(new LogRecordContentHandler);

            return $contentProcessingStrategy;
        });
    }
}
