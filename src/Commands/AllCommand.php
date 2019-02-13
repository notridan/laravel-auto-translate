<?php

namespace Ben182\AutoTranslate\Commands;

use Illuminate\Support\Arr;
use Illuminate\Console\Command;
use Ben182\AutoTranslate\AutoTranslate;

class AllCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'autotrans:all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Translates all source translations to target translations';

    protected $autoTranslator;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(AutoTranslate $autoTranslator)
    {
        parent::__construct();
        $this->autoTranslator = $autoTranslator;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $targetLanguages = Arr::wrap(config('auto-translate.target_language'));

        foreach ($targetLanguages as $targetLanguage) {
            $sourceTranslations = $this->autoTranslator->getSourceTranslations();

            $translated = $this->autoTranslator->translate($targetLanguage, $sourceTranslations);

            $this->autoTranslator->fillLanguageFiles($targetLanguage, $translated);
        }

        $this->info('Translated '.count(Arr::dot($sourceTranslations)).' translations.');
    }
}
