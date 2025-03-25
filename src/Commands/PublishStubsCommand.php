<?php

namespace KnausDev\PackageGenerator\Commands;

use Illuminate\Console\Command;
use KnausDev\PackageGenerator\Support\StubProcessor;

class PublishStubsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'knausdev:publish-stubs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish stubs for customization';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $stubProcessor = new StubProcessor();
        $destination = base_path('stubs/vendor/knausdev/package-generator');

        $this->info('Publishing stubs for customization...');

        if ($stubProcessor->publishStubs($destination)) {
            $this->info('Stubs published successfully to: ' . $destination);
            return Command::SUCCESS;
        }

        $this->error('Failed to publish stubs');
        return Command::FAILURE;
    }
}
