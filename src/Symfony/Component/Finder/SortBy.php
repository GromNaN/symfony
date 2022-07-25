<?php

namespace Symfony\Component\Finder;

/**
 *
 */
enum SortBy
{
    case None;
    case Extension;
    case Type;
    case AccessedTime;
    case ChangedTime;
    case ModifiedTime;
    case Name;
    case NameNatural;
    case NameCaseInsensitive;
    case NameNaturalCaseInsensitive;
    case Size;
}
