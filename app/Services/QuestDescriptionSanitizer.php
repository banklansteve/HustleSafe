<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

class QuestDescriptionSanitizer
{
    public function clean(string $html): string
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        // HTMLPurifier's default doctypes (e.g. HTML 4.01) do not register <mark>; listing it in HTML.Allowed
        // triggers "Element 'mark' is not supported". TipTap/paste may still send <mark> — it is stripped on purify.
        $config->set(
            'HTML.Allowed',
            'p[style],br,strong,b,em,u,s,strike,sub,sup,h2[style],h3[style],ul[style],ol[style],li[style],blockquote[style],a[href|title|target|rel],span[style],code',
        );
        $config->set('CSS.AllowedProperties', 'color,background-color,text-align');
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);

        $purifier = new HTMLPurifier($config);

        return $purifier->purify($html);
    }
}
