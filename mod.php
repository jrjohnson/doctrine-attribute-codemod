<?php

declare(strict_types=1);

use Codeshift\AbstractCodemod;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class EntityVisitor extends NodeVisitorAbstract
{
    protected string $doctrinePrefix = 'Doctrine\ORM\Mapping';
    public function beforeTraverse(array $nodes)
    {
        foreach ($nodes as $node) {
            if ($node instanceof Node\Stmt\Use_) {
                $this->determineDocBlockPrefix($node);
            }
            if ($node instanceof Node\Stmt\Namespace_) {
                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof Node\Stmt\Use_) {
                        $this->determineDocBlockPrefix($stmt);
                    }
                }
            }
        }
    }

    public function afterTraverse(array $nodes)
    {
        foreach ($nodes as $node) {
            if ($node instanceof Node\Stmt\Class_) {
                $this->parseClass($node);
            }
            if ($node instanceof Node\Stmt\Namespace_) {
                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof Node\Stmt\Class_) {
                        $this->parseClass($stmt);
                    }
                }
            }
        }
    }

    protected function determineDocBlockPrefix(Node\Stmt\Use_ $node): void
    {
        foreach ($node->uses as $use) {
            if ($use->name->parts == ['Doctrine', 'ORM', 'Mapping']) {
                $this->doctrinePrefix = $use->alias ? $use->alias->toString() : 'Mapping';
            }
        }
    }

    protected function getAttributesFromString(string $string): array
    {
        $pattern = '|@' . $this->doctrinePrefix . '\\\([a-zA-Z]+)(.*)|';

        if (preg_match_all($pattern, $string, $matches)) {
            $arguments = [];
            if (isset($matches[2])) {
                $val = trim($matches[2][0], '()');
                foreach(explode(',', $val) as $arg) {
                    $arr = explode('=', $arg);
                    if (isset($arr[1])) {
                        $arguments[] = [
                            'name' => $arr[0],
                            'value' => trim($arr[1], '"\'')
                        ];
                    }
                }
            }
            return [
                'name' => $matches[1][0],
                'arguments' => $arguments
            ];
        }

        return [];
    }

    protected function parseProperty(Node\Stmt\Property $node): void
    {
        $docBlock = $node->getDocComment();
        if (!$docBlock) {
            return;
        }
        $string = $docBlock->getReformattedText();
        $lines = explode("\n", $string);
        $linesWithORMAnnotations = array_filter($lines, fn(string $line) => str_contains($line, '@' . $this->doctrinePrefix));
        $linesWithoutORMAnnotations = array_filter($lines, fn(string $line) => !str_contains($line, '@' . $this->doctrinePrefix));
        $linesWithoutEmptyLines = array_filter($linesWithoutORMAnnotations, fn(string $line) => strcmp($line, ' *') !== 0);
        $node->setDocComment(new Doc(implode("\n", $linesWithoutEmptyLines)));

        foreach ($linesWithORMAnnotations as $str) {
            $annotation = $this->getAttributesFromString($str);
            if ($annotation) {
                $arguments = array_map(function (array $arr) {
                    $name = trim($arr['name']);
                    $value = match($name) {
                        'length' => new Node\Scalar\LNumber((int) $arr['value']),
                        'nullable', 'unique' => new Node\Expr\ConstFetch(new Node\Name($arr['value'])),
                        default => new Node\Scalar\String_($arr['value'])
                    };
                    return new Node\Arg(
                        $value,
                        false,
                        false,
                        [],
                        new Node\Identifier($name)
                    );
                }, $annotation['arguments']);
                $attribute = new Node\Attribute(new Node\Name($this->doctrinePrefix . '\\' . $annotation['name']), $arguments);

                $node->attrGroups[] = new Node\AttributeGroup([$attribute]);
            }
        }
    }

    protected function parseClass(Node\Stmt\Class_ $node): void
    {
        $docBlock = $node->getDocComment();
        if (!$docBlock) {
            return;
        }
        $string = $docBlock->getReformattedText();
        $lines = explode("\n", $string);
        $linesWithORMAnnotations = array_filter($lines, fn(string $line) => str_contains($line, '@' . $this->doctrinePrefix));
        $linesWithoutORMAnnotations = array_filter($lines, fn(string $line) => !str_contains($line, '@' . $this->doctrinePrefix));
        $linesWithoutEmptyLines = array_filter($linesWithoutORMAnnotations, fn(string $line) => strcmp($line, ' *') !== 0);
        $node->setDocComment(new \PhpParser\Comment\Doc(implode("\n", $linesWithoutEmptyLines)));

        foreach ($linesWithORMAnnotations as $str) {
            $annotation = $this->getAttributesFromString($str);
            if ($annotation) {
                $arguments = array_map(function (array $arr) {
                    $name = trim($arr['name']);
                    $value = match($name) {
                        'repositoryClass' =>
                            new Node\Expr\ClassConstFetch(new Node\Name(rtrim($arr['value'], '::class')),
                            'class'
                        ),
                        default => new Node\Scalar\String_($arr['value'])
                    };
                    return new Node\Arg(
                        $value,
                        false,
                        false,
                        [],
                        new Node\Identifier($name)
                    );
                }, $annotation['arguments']);
                $attribute = new Node\Attribute(new Node\Name($this->doctrinePrefix . '\\' . $annotation['name']), $arguments);

                $node->attrGroups[] = new Node\AttributeGroup([$attribute]);
            }
        }
        foreach ($node->getProperties() as $property) {
            $this->parseProperty($property);
        }
    }
}

class Mod extends AbstractCodemod
{
    public function init() {
        $entityVisitor = new EntityVisitor();
        $this->addTraversalTransform($entityVisitor);
    }
}

return Mod::class;