<?php

declare(strict_types=1);

namespace Sidux\PhpGenerator\Helper;

use phpDocumentor\Reflection\DocBlock\Tags\Return_;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\TypeResolver;
use phpDocumentor\Reflection\Types\Context;
use PHPStan\BetterReflection\BetterReflection;
use ReflectionClass;
use ReflectionFunction;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionMethod;

final class ReflectionHelper
{
    public static function getBodyCode(ReflectionFunction|ReflectionMethod|\PHPStan\BetterReflection\Reflection\ReflectionFunction $reflection, bool $isInterface = false): string
    {
        if ($isInterface || ($reflection instanceof ReflectionMethod && $reflection->isAbstract())) {
            return '';
        }

        $filename = $reflection->getFileName();

        if ($filename === false) {
            throw new \LogicException("Filename is not set");
        }

        $file = file($filename);

        if ($file === false) {
            throw new \LogicException("Unable to open file {$filename}.");
        }

        $startLine = self::getBodyCodeStartLine($reflection->getName(), $file);
        $endLine = self::getBodyCodeEndLine($reflection->getName(), $file);

        $length = $endLine - $startLine + 1;

        if ($length <= 2) {
            $length = 0;
        }

        $code = \array_slice($file, $startLine, $length);

        return trim(preg_replace('/\s+{\n|\s+}\n/', '', implode('', $code)));
    }

    public static function getBodyCodeStartLine(string $functionOrMethodName, array $file): int
    {
        $startLine = 0;
        $found = false;

        foreach ($file as $lineNumber => $line) {
            if (str_contains($line, $functionOrMethodName)) {
                $startLine = $lineNumber;
                $found = true;
            }

            if ($found && str_contains($line, '{')) {
                $startLine = $lineNumber;
                break;
            }
        }

        return $startLine;
    }

    public static function getBodyCodeEndLine(string $functionOrMethodName, array $file): int
    {
        $endLine = 0;
        $found = false;

        foreach ($file as $lineNumber => $line) {
            if (str_contains($line, $functionOrMethodName)) {
                $endLine = $lineNumber;
                $found = true;
            }

            if ($found && str_contains($line, '}')) {
                $endLine = $lineNumber;
                break;
            }
        }

        return $endLine;
    }

    public static function getDocBlockReturnTypes(ReflectionFunction|ReflectionMethod|\PHPStan\BetterReflection\Reflection\ReflectionFunction $reflection): array
    {
        $docBlockFactory = DocBlockFactory::createInstance();
        $typeResolver = new TypeResolver();
        $docComment = $reflection->getDocComment();
        $context = $reflection->getNamespaceName() ? new Context($reflection->getNamespaceName()) : null;

        if ($docComment === false) {
            return [];
        }

        $returnTags = $docBlockFactory
            ->create($docComment, $context)
            ->getTagsByName('return');

        $types = [];

        foreach ($returnTags as $returnTag) {
            /** @var Return_ $returnTag */
            $returnTypes = explode('|', (string)$returnTag->getType());

            foreach ($returnTypes as $returnType) {
                $type = $typeResolver->resolve($returnType, $context);
                $types[] = $type;
            }
        }

        return $types;
    }

    public static function getDocBlockTypes(ReflectionParameter|ReflectionProperty|\PHPStan\BetterReflection\Reflection\ReflectionParameter $reflection): array
    {
        $docBlockFactory = DocBlockFactory::createInstance();
        $typeResolver = new TypeResolver();

        if ($reflection instanceof ReflectionProperty) {
            $docComment = $reflection->getDocComment();
            $context = new Context($reflection->getDeclaringClass()->getNamespaceName());

        } else {
            $docComment = $reflection->getDeclaringFunction()->getDocComment();
            $namespace = $reflection->getDeclaringFunction()->getNamespaceName();
            $context = $namespace ? new Context($reflection->getDeclaringFunction()->getNamespaceName()) : null;
        }

        if ($docComment === false) {
            return [];
        }

        $paramTags = $docBlockFactory
            ->create($docComment, $context)
            ->getTagsByName('param');

        $types = [];

        foreach ($paramTags as $paramTag) {
            /** @var \phpDocumentor\Reflection\DocBlock\Tags\Param $paramTag */
            $paramTypes = explode('|', (string)$paramTag->getType());

            foreach ($paramTypes as $paramType) {
                $type = $typeResolver->resolve($paramType, $context);
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * @throws \Exception
     */
    public static function createReflectionMethodFromName(string $className, string $methodName): ReflectionMethod
    {
        try {
            return self::createClassFromName($className)->getMethod($methodName);
        } catch (\ReflectionException) {
            throw new \Exception(sprintf('Could not find class: %s', $className));
        }
    }

    /**
     * @throws \Exception
     */
    public static function createReflectionFunctionFromName(string $functionName): \PHPStan\BetterReflection\Reflection\ReflectionFunction
    {
        return (new BetterReflection())->reflector()->reflectFunction($functionName);
    }

    /**
     * @throws \ReflectionException
     */
    public static function createClassFromName(string $className): ReflectionClass
    {
        return new ReflectionClass($className);
    }

    /**
     * @throws \ReflectionException
     */
    public static function createFunctionFromClosure(\Closure $closure): ReflectionFunction
    {
        return new ReflectionFunction($closure);
    }

    public static function createClassFromInstance(object $instance): ReflectionClass
    {
        return new ReflectionClass($instance);
    }

    public static function createPropertyFromName(string $className, string $propertyName): ReflectionProperty
    {
        return self::createClassFromName($className)->getProperty($propertyName);
    }

    public static function createPropertyFromInstance(object $instance, string $propertyName): ReflectionProperty
    {
        return self::createClassFromInstance($instance)->getProperty($propertyName);
    }

    /**
     * @throws \ReflectionException
     */
    public static function createParameterFromInstanceAndMethod(object $instance, string $methodName, string $parameterName): ReflectionParameter
    {
        $parameters = self::createClassFromInstance($instance)->getMethod($methodName)->getParameters();

        foreach ($parameters as $parameter) {
            if ($parameter->getName() === $parameterName) {
                return $parameter;
            }
        }

        throw new \ReflectionException(sprintf('Could not find parameter: %s', $parameterName));
    }

    public static function createParameterFromClassNameAndMethod(string $className, string $methodName, string $parameterName): ReflectionParameter
    {
        $parameters = self::createClassFromName($className)->getMethod($methodName)->getParameters();

        foreach ($parameters as $parameter) {
            if ($parameter->getName() === $parameterName) {
                return $parameter;
            }
        }

        throw new \ReflectionException(sprintf('Could not find parameter: %s', $parameterName));
    }
}
