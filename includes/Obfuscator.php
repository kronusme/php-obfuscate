<?php

class Obfuscator {

    protected $source = '';

    /**
     * @param string $input
     */
    public function __construct($input) {
        $this->source = (string)$input;
    }

    public function obfuscate() {
        $content = $this->source;
        $parser = new PhpParser\Parser(new PhpParser\Lexer\Emulative);
        $traverser = new PhpParser\NodeTraverser;
        $prettyPrinter = new PhpParser\PrettyPrinter\Obfuscated;

        // add your visitors
        $traverser->addVisitor(new StringNodeVisitor);
        $traverser->addVisitor(new VariableNodeVisitor);
        $traverser->addVisitor(new DigitNodeVisitor);
        $f = new FunctionNodeVisitor;
        $traverser->addVisitor($f);

        try {
            $stmts = $parser->parse($content);
            $stmts = $traverser->traverse($stmts);
            $nodeWithFunctionNames = $f->getNativeFunctionNamesNode();
            $userDefinedFunctionNames = $f->getUserDefinedFunctionNames();

            $f2 = new Function2NodeVisitor($nodeWithFunctionNames, $userDefinedFunctionNames);

            $traverser2 = new PhpParser\NodeTraverser;
            $traverser2->addVisitor($f2);
            $stmts = array_merge(array($nodeWithFunctionNames['']), $stmts);
            $stmts = $traverser2->traverse($stmts);

            $content = $prettyPrinter->prettyPrintFile($stmts);

            return $content;
        }
        catch (PhpParser\Error $e) {
            return 'Parse Error: '.$e->getMessage();
        }
    }

}