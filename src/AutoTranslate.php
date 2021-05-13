<?php

namespace Ben182\AutoTranslate;

use Illuminate\Support\Arr;
use Themsaid\Langman\Manager as Langman;
use Ben182\AutoTranslate\Translators\TranslatorInterface;

class AutoTranslate
{
    protected $manager;
    public $translator;

    public function __construct(Langman $manager, TranslatorInterface $translator)
    {
        $this->manager = $manager;
        $this->translator = $translator;
        $this->translator->setSource(config('auto-translate.source_language'));
    }

    public function getSourceTranslations()
    {
        return $this->getTranslations(config('auto-translate.source_language'));
    }

    public function getTranslations(string $lang)
    {
        $aReturn = [];

        $files = $this->manager->files();

        foreach ($files as $fileKeyName => $languagesFile) {
            if (! isset($languagesFile[$lang])) {
                continue;
            }

            $allTranslations = $this->manager->getFileContent($languagesFile[$lang]);

            $aReturn[$fileKeyName] = $allTranslations;
        }

        if(file_exists(config('auto-translate.path') . DIRECTORY_SEPARATOR . $lang . '.json')){
            $json = file_get_contents(config('auto-translate.path') . DIRECTORY_SEPARATOR . $lang . '.json');
            $translations = (array)json_decode($json);
            $aReturn['strings_as_keys'] = $translations;
        }
        return $aReturn;
    }

    public function getMissingTranslations(string $lang)
    {
        $source = $this->getSourceTranslations();
        $lang = $this->getTranslations($lang);

        $dottedSource = Arr::dot($source);
        $dottedlang = Arr::dot($lang);

        $diff = array_diff(array_keys($dottedSource), array_keys($dottedlang));

        return collect($dottedSource)->only($diff);
    }

    public function translate(string $targetLanguage, $data, $callbackAfterEachTranslation = null)
    {
        $this->translator->setTarget($targetLanguage);

        $dottedSource = Arr::dot($data);

        foreach ($dottedSource as $key => $value) {
            if ($value === '') {
                $dottedSource[$key] = $value;

                if ($callbackAfterEachTranslation) {
                    $callbackAfterEachTranslation();
                }
                continue;
            }

            $variables = $this->findVariables($value);

            $masked = array();
            $count = 0;
            foreach ($variables as $set) {
                foreach ($set as $var) {
                    $count++;
                    $masked[$var] = '___' . $count . '__';
                    $value = str_replace($var, $masked[$var],$value );
                }
            }
            $dottedSource[$key] = is_string($value) ? $this->translator->translate($value) : $value;

            $dottedSource[$key] = $this->replaceTranslatedVariablesWithOld($masked, $dottedSource[$key]);

            if ($callbackAfterEachTranslation) {
                $callbackAfterEachTranslation();
            }
        }

        return $this->array_undot($dottedSource);
    }

    public function findVariables($string)
    {
        $m = null;

        if (is_string($string)) {
            preg_match_all('/:\S+/', $string, $m);
        }

        return $m;
    }

    public function replaceTranslatedVariablesWithOld($masked, $string)
    {
        foreach ($masked as $var => $mask) {
            $string = str_replace($mask, $var, $string);
        }
        return $string;
    }

    public function fillLanguageFiles(string $language, array $data)
    {
        foreach ($data as $languageFileKey => $translations) {
            $translations = array_map(function ($item) use ($language) {
                return [
                    $language => $item,
                ];
            }, $translations);
            if ($languageFileKey != 'strings_as_keys'){
                $this->manager->fillKeys($languageFileKey, $translations);
            }else{
                $this->fillStringsAsKeys($language,$data);
            }
        }
    }


    public function fillStringsAsKeys(string $language, array $keys)
    {
        $translations = array();

        // for some reason, sometimes the translations themselves are an array, reduce that
        foreach ($keys['strings_as_keys'] as $key => $value) {
            if(is_array($value)){
                $value = array_pop($value);
            }
            $translations[$key] = $value;
        }

        $filePath = config('auto-translate.path') . DIRECTORY_SEPARATOR . $language . '.json';
        if (file_exists($filePath)){
            $json = file_get_contents($filePath);
            $existing = (array)json_decode($json);
        }else{
            $existing = array();
        }

        $newContent = array_replace_recursive($existing, $translations);

        file_put_contents($filePath, json_encode($newContent,JSON_PRETTY_PRINT));
    }

    public function array_undot(array $dottedArray, array $initialArray = []) : array
    {
        foreach ($dottedArray as $key => $value) {
            Arr::set($initialArray, $key, $value);
        }

        return $initialArray;
    }
}
