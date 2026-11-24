<?php

namespace CodeQ\AdvancedPublish\Exception;

use Neos\Flow\Exception;

class ConcurrentPublicationException extends Exception
{
    public static function nothingLeftToPublish(): self
    {
        return new self('Diese Änderungen wurden bereits veröffentlicht, z. B. durch einen Doppelklick oder eine parallele Freigabe.', 1795516835123);
    }

    public static function stillInProgress(): self
    {
        return new self('Dieser Arbeitsbereich wird gerade in einer anderen Anfrage veröffentlicht. Bitte prüfe die Liste der Veröffentlichungen in ein paar Minuten.', 1795516835124);
    }
}
