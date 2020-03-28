<?php

namespace HMVCTools\Console\Generators;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use stdClass;

class MakeModule extends Command
{
    /**
     * @var string
     */
    protected $signature = 'module:create
        {alias : The alias of the module}
    ';

    /**
     * @var string
     */
    protected $description = 'WebEd modules generator.';

    /**
     * @var Filesystem
     */
    protected $files;

    /**
     * Array to store the configuration details.
     *
     * @var array
     */
    protected $container = [];

    /**
     * Accepted module types
     * @var array
     */
    protected $acceptedTypes = [
        'core' => 'Core',
        'plugins' => 'Plugins',
        'themes' => 'Themes',
    ];

    protected $moduleType;

    protected $moduleFolderName;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Filesystem $filesystem)
    {
        parent::__construct();

        $this->files = $filesystem;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->moduleType = $this->ask('Your module type. Accepted: core, plugins, themes.', 'plugins');

        if (!in_array($this->moduleType, array_keys($this->acceptedTypes))) {
            $this->moduleType = 'plugins';
        }

        $directory = base_path('platform/' . $this->moduleType);

        if (!File::exists($directory)) {
            File::makeDirectory($directory);
        }

        $this->container['alias'] = Str::slug($this->argument('alias'));

        $this->step1();

        $this->step2();
    }

    protected function step1()
    {
        $this->moduleFolderName = Str::slug($this->ask('Module folder name:', $this->container['alias']));

        $directory = base_path('platform/' . $this->moduleType . '/' . $this->moduleFolderName);
        if (File::exists($directory)) {
            $this->error('The module path already exists');

            exit();
        }

        $this->container['description'] = (string)$this->ask('Description of module:');
        $this->container['namespace'] = $this->ask('Namespace of module:', $this->acceptedTypes[$this->moduleType] . '\\' . Str::studly($this->container['alias']));
    }

    protected function step2()
    {
        $this->generatingModule();

        $this->info("Your module generated successfully.");
    }

    protected function generatingModule()
    {
        $directory = base_path('platform/' . $this->moduleType . '/' . $this->moduleFolderName);

        if ($this->moduleType == 'theme') {
            $source = __DIR__ . '/../../../../resources/structures/theme';
        } else {
            $source = __DIR__ . '/../../../../resources/structures/module';
        }

        /**
         * Make directory
         */
        $this->files->makeDirectory($directory);
        $this->files->copyDirectory($source, $directory, null);

        try {
            $composerJSON = json_decode(File::get($directory . '/composer.json'), true);

            $composerJSON['name'] = $this->moduleType . '/' . $this->container['alias'];
            $composerJSON['description'] = $this->container['description'];
            $composerJSON['autoload']['psr-4'][$this->container['namespace'] . '\\'] = 'src/';
            $composerJSON['require'] = new stdClass();
            $composerJSON['require-dev'] = new stdClass();

            File::put($directory . '/composer.json', json_encode_prettify($composerJSON));

            $moduleJSON = [];
            $moduleJSON = array_merge($moduleJSON, $this->container);

            File::put($directory . '/module.json', json_encode_prettify($moduleJSON));

            /**
             * Replace files placeholder
             */
            $files = $this->files->allFiles($directory);
            foreach ($files as $file) {
                $contents = $this->replacePlaceholders($file->getContents());
                $filePath = base_path('platform/' . $this->moduleType . '/' . $this->moduleFolderName . '/' . $file->getRelativePathname());

                $this->files->put($filePath, $contents);
            }
        } catch (Exception $exception) {
            $this->files->deleteDirectory($directory);

            $this->error($exception->getMessage());
        }
    }

    protected function replacePlaceholders($contents)
    {
        $find = [
            'DummyNamespace',
            'DummyAlias',
            'DummyType',
        ];

        $replace = [
            $this->container['namespace'],
            $this->container['alias'],
            $this->moduleType,
        ];

        return str_replace($find, $replace, $contents);
    }
}