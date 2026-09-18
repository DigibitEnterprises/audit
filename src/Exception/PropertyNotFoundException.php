<?php declare(strict_types=1);

namespace Digibit\Audit\Exception;

/** Thrown by PropertyLocator::find() when the named property doesn't exist anywhere in the class hierarchy. */
final class PropertyNotFoundException extends LogicException
{
}
