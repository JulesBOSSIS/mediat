<?php

namespace App\DQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

class RegexpFunction extends FunctionNode
{
    public $field;
    public $pattern;

    public function parse(Parser $parser): void
    {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);
        $this->field = $parser->InExpression();
        $parser->match(Lexer::T_COMMA);
        $this->pattern = $parser->StringPrimary();
        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        return $this->field->dispatch($sqlWalker) . ' REGEXP ' . $this->pattern->dispatch($sqlWalker);
    }

    public function getReturnType(): string
    {
        return 'boolean';
    }
}
