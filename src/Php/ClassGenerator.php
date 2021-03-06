<?php
namespace GoetasWebservices\Xsd\XsdToPhp\Php;

use Doctrine\Common\Inflector\Inflector;
use GoetasWebservices\Xsd\XsdToPhp\Php\Structure\PHPClass;
use GoetasWebservices\Xsd\XsdToPhp\Php\Structure\PHPClassOf;
use GoetasWebservices\Xsd\XsdToPhp\Php\Structure\PHPProperty;
use Zend\Code\Generator;
use Zend\Code\Generator\DocBlock\Tag\ParamTag;
use Zend\Code\Generator\DocBlock\Tag\PropertyTag;
use Zend\Code\Generator\DocBlock\Tag\ReturnTag;
use Zend\Code\Generator\DocBlock\Tag\GenericTag;
use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\ParameterGenerator;
use Zend\Code\Generator\PropertyGenerator;

class ClassGenerator
{

    private function handleBody(Generator\ClassGenerator $class, PHPClass $type)
    {
        foreach ($type->getProperties() as $prop) {
            if ($prop->getName() !== '__value') {
                $this->handleProperty($class, $prop);
            }
        }
        foreach ($type->getProperties() as $prop) {
            if ($prop->getName() !== '__value') {
                $this->handleMethod($class, $prop, $type);
            }
        }

        if (count($type->getProperties()) === 1 && $type->hasProperty('__value')) {
            return false;
        }

        return true;
    }

    private function isNativeType(PHPClass $class)
    {
        return !$class->getNamespace() && in_array($class->getName(), [
            'string',
            'int',
            'float',
            'integer',
            'boolean',
            'array',
            'mixed',
            'callable'
        ]);
    }
    
    private function getAvailableChecks(PHPProperty $prop, PHPClass $class, $checkName, $nativeType = null) 
    {
        $checks = $class->getChecks('__value');
        if (count($checks) === 0) {
            return false;
        }
        
        if (!isset($checks[$checkName])) {
            return false;
        }
        
        $check = $checks[$checkName];
        if (count($check) === 0) {
            return false;
        }
        
        $type = $prop->getType();
        if ($type && !$type->isNativeType()) {
            return false;
        }
        
        if (isset($nativeType) && $type->getName() !== $nativeType) {
            return false;
        }
        
        return $check;
    }

    /**
     * Defines a list of acceptable values
     * 
     * @param \Zend\Code\Generator\ClassGenerator $generator
     * @param PHPProperty $prop
     * @param PHPClass $class
     */
    private function handleValueEnumeration(Generator\ClassGenerator $generator, PHPProperty $prop, PHPClass $class) 
    {
        if (!$checks = $this->getAvailableChecks( $prop, $class, 'enumeration' )) {
            return;
        }
        
        $type = $prop->getType();        
        $typeDefinition = 'mixed';
        if ($type && $type instanceof PHPClassOf) {
            $typeDefinition = $type->getArg()->getType()->getPhpType();
        } elseif ($type) {
            $typeDefinition = $prop->getType()->getPhpType();
        }
        
        // const
        $constNames = array();
        foreach ($checks as $enum) {
            $constName = 'V_' . strtoupper( preg_replace( '/[^0-9a-zA-Z]+/i', '_', $enum['value'] ) );
            $constValue = $enum['value'];
            $constNames[] = $constName;
            
            if ($generator->getConstant($constName) === false) {
                $docblock = new DocBlockGenerator();
                $paramTag = new GenericTag("var", $typeDefinition);
                $docblock->setTag($paramTag);
            
                $const = new PropertyGenerator($constName, $constValue);
                $const->setConst(true);
                $const->setDocBlock($docblock);            

                $generator->addPropertyFromGenerator($const);
            }
            
        }
        
        // public static function values()	
        $docblock = new DocBlockGenerator('Gets all possible value');
        $docblock->setTag(new ReturnTag("array"));
        
        $method = new MethodGenerator("values");
        $method->setStatic(true);
        $method->setDocBlock($docblock);
        $method->setBody("return array(" . 
            implode(', ', array_map(function($value){
                return 'self::' . $value;
            }, $constNames)) . 
        ");");
            
        $generator->addMethodFromGenerator($method);

        // add from `protected function _checkRestrictions($value)`
        $method = $generator->getMethod('validate');
        $methodBody = "\RestrictionUtils::checkEnumeration(\$this->value, static::values());" . PHP_EOL;        
        $method->setBody( $method->getBody() . $methodBody );
    }

    /**
     * Defines the exact sequence of characters that are acceptable 
     * 
     * @param \Zend\Code\Generator\ClassGenerator $generator
     * @param PHPProperty $prop
     * @param PHPClass $class
     */
    private function handleValuePattern(Generator\ClassGenerator $generator, PHPProperty $prop, PHPClass $class) 
    {
        if (!$checks = $this->getAvailableChecks( $prop, $class, 'pattern' )) {
            return;
        }
        // add from `protected function _checkRestrictions($value)`
        $methodBody = "";
        foreach ($checks as $pattern) {
            $methodBody .= "RestrictionUtils::checkPattern(\$this->value, \"{$pattern['value']}\");" . PHP_EOL;
        }
        $method = $generator->getMethod('validate');
        $method->setBody( $method->getBody() . $methodBody );
    }
    
    /**
     * Specifies the maximum number of decimal places allowed. 
     * 
     * @param \Zend\Code\Generator\ClassGenerator $generator
     * @param PHPProperty $prop
     * @param PHPClass $class
     */
    private function handleValueFractionDigits(Generator\ClassGenerator $generator, PHPProperty $prop, PHPClass $class) 
    {
        if (!$checks = $this->getAvailableChecks( $prop, $class, 'fractionDigits', 'float' )) {
            return;
        }
        // add from `protected function _checkRestrictions($value)`
        $methodBody = "";
        foreach ($checks as $fractionDigits) {
            $methodBody .= "RestrictionUtils::checkFractionDigits(\$this->value, {$fractionDigits['value']});" . PHP_EOL;
        }
        $method = $generator->getMethod('validate');
        $method->setBody( $method->getBody() . $methodBody );
    }
    
    /**
     * Specifies the exact number of digits allowed. Must be greater than zero
     * 
     * @param \Zend\Code\Generator\ClassGenerator $generator
     * @param PHPProperty $prop
     * @param PHPClass $class
     */
    private function handleValueTotalDigits(Generator\ClassGenerator $generator, PHPProperty $prop, PHPClass $class) 
    {
        if (!$checks = $this->getAvailableChecks( $prop, $class, 'totalDigits' )) {
            return;
        }
        // add from `protected function _checkRestrictions($value)`
        $methodBody = "";
        foreach ($checks as $totalDigits) {
            $methodBody .= "RestrictionUtils::checkTotalDigits(\$this->value, {$totalDigits['value']});" . PHP_EOL;
        }
        $method = $generator->getMethod('validate');
        $method->setBody( $method->getBody() . $methodBody );
    }
        
    /**
     * Specifies the exact number of characters or list items allowed. 
     * 
     * @param \Zend\Code\Generator\ClassGenerator $generator
     * @param PHPProperty $prop
     * @param PHPClass $class
     * @return type
     */
    private function handleValueLength(Generator\ClassGenerator $generator, PHPProperty $prop, PHPClass $class) 
    {
        if (!$checks = $this->getAvailableChecks( $prop, $class, 'length' )) {
            return;
        }
        $nativeType = 'mixed';
        $type = $prop->getType();
        if ($type && $type->isNativeType()) {
            $nativeType = $type->getName();
        }
        // add from `protected function _checkRestrictions($value)`
        $methodBody = "";
        foreach ($checks as $length) {
            $methodBody .= "RestrictionUtils::checkLength(\$this->value, {$length['value']}, \"{$nativeType}\");" . PHP_EOL;
        }
        $method = $generator->getMethod('validate');
        $method->setBody( $method->getBody() . $methodBody );
    }
    
    /**
     * Specifies the maximum number of characters or list items allowed. Must be equal to or greater than zero.
     * 
     * @param \Zend\Code\Generator\ClassGenerator $generator
     * @param PHPProperty $prop
     * @param PHPClass $class
     * @return type
     */
    private function handleValueMaxLength(Generator\ClassGenerator $generator, PHPProperty $prop, PHPClass $class) 
    {
        if (!$checks = $this->getAvailableChecks( $prop, $class, 'maxLength' )) {
            return;
        }
        $nativeType = 'mixed';
        $type = $prop->getType();
        if ($type && $type->isNativeType()) {
            $nativeType = $type->getName();
        }
        // add from `protected function _checkRestrictions($value)`
        $methodBody = "";
        foreach ($checks as $maxLength) {
            $methodBody .= "RestrictionUtils::checkMaxLength(\$this->value, {$maxLength['value']}, \"{$nativeType}\");" . PHP_EOL;
        }
        $method = $generator->getMethod('validate');
        $method->setBody( $method->getBody() . $methodBody );
    }    
    
    /**
     * Specifies the maximum number of characters or list items allowed. Must be equal to or greater than zero.
     * 
     * @param \Zend\Code\Generator\ClassGenerator $generator
     * @param PHPProperty $prop
     * @param PHPClass $class
     * @return type
     */
    private function handleValueMinLength(Generator\ClassGenerator $generator, PHPProperty $prop, PHPClass $class) 
    {
        if (!$checks = $this->getAvailableChecks( $prop, $class, 'minLength' )) {
            return;
        }
        $nativeType = 'mixed';
        $type = $prop->getType();
        if ($type && $type->isNativeType()) {
            $nativeType = $type->getName();
        }
        // add from `protected function _checkRestrictions($value)`
        $methodBody = "";
        foreach ($checks as $minLength) {
            $methodBody .= "RestrictionUtils::checkMinLength(\$this->value, {$minLength['value']}, \"{$nativeType}\");" . PHP_EOL;
        }
        $method = $generator->getMethod('validate');
        $method->setBody( $method->getBody() . $methodBody );
    }        
    
    /**
     * Specifies the upper bounds for numeric values (the value must be less than this value)
     * 
     * @param \Zend\Code\Generator\ClassGenerator $generator
     * @param PHPProperty $prop
     * @param PHPClass $class
     */
    private function handleValueMaxExclusive(Generator\ClassGenerator $generator, PHPProperty $prop, PHPClass $class) 
    {
        if (!$checks = $this->getAvailableChecks( $prop, $class, 'maxExclusive' )) {
            return;
        }
        // add from `protected function _checkRestrictions($value)`
        $methodBody = "";
        foreach ($checks as $maxExclusive) {
            $methodBody .= "RestrictionUtils::checkMaxExclusive(\$this->value, {$maxExclusive['value']});" . PHP_EOL;
        }
        $method = $generator->getMethod('validate');
        $method->setBody( $method->getBody() . $methodBody );
    }
    
    /**
     * Specifies the lower bounds for numeric values (the value must be greater than this value)
     * 
     * @param \Zend\Code\Generator\ClassGenerator $generator
     * @param PHPProperty $prop
     * @param PHPClass $class
     */
    private function handleValueMinExclusive(Generator\ClassGenerator $generator, PHPProperty $prop, PHPClass $class) 
    {
        if (!$checks = $this->getAvailableChecks( $prop, $class, 'minExclusive' )) {
            return;
        }
        // add from `protected function _checkRestrictions($value)`
        $methodBody = "";
        foreach ($checks as $minExclusive) {
            $methodBody .= "RestrictionUtils::checkMinExclusive(\$this->value, {$minExclusive['value']});" . PHP_EOL;
        }
        $method = $generator->getMethod('validate');
        $method->setBody( $method->getBody() . $methodBody );
    }
    
    /**
     * Specifies the upper bounds for numeric values (the value must be less than or equal to this value)
     * 
     * @param \Zend\Code\Generator\ClassGenerator $generator
     * @param PHPProperty $prop
     * @param PHPClass $class
     */
    private function handleValueMaxInclusive(Generator\ClassGenerator $generator, PHPProperty $prop, PHPClass $class) 
    {
        if (!$checks = $this->getAvailableChecks( $prop, $class, 'maxInclusive' )) {
            return;
        }
        // add from `protected function _checkRestrictions($value)`
        $methodBody = "";
        foreach ($checks as $maxInclusive) {
            $methodBody .= "RestrictionUtils::checkMaxInclusive(\$this->value, {$maxInclusive['value']});" . PHP_EOL;
        }
        $method = $generator->getMethod('validate');
        $method->setBody( $method->getBody() . $methodBody );
    }
    
    /**
     * Specifies the lower bounds for numeric values (the value must be greater than or equal to this value)
     * 
     * @param \Zend\Code\Generator\ClassGenerator $generator
     * @param PHPProperty $prop
     * @param PHPClass $class
     */
    private function handleValueMinInclusive(Generator\ClassGenerator $generator, PHPProperty $prop, PHPClass $class) 
    {
        if (!$checks = $this->getAvailableChecks( $prop, $class, 'minInclusive' )) {
            return;
        }
        // add from `protected function _checkRestrictions($value)`
        $methodBody = "";
        foreach ($checks as $minInclusive) {
            $methodBody .= "RestrictionUtils::checkMinInclusive(\$this->value, {$minInclusive['value']});" . PHP_EOL;
        }
        $method = $generator->getMethod('validate');
        $method->setBody( $method->getBody() . $methodBody );
    }
    
    private function handleValueMethod(Generator\ClassGenerator $generator, PHPProperty $prop, PHPClass $class, $all = true)
    {
        $type = $prop->getType();
        $typeDefinition = 'mixed';
        if ($type && $type instanceof PHPClassOf) {
            $typeDefinition = $type->getArg()->getType()->getPhpType();
        } elseif ($type) {
            $typeDefinition = $prop->getType()->getPhpType();
        }
        
        //
        // public function __construct($value)
        //
        $docblock = new DocBlockGenerator('Construct');
        $paramTag = new ParamTag("value", "mixed");
        $paramTag->setTypes(($type ? $type->getPhpType() : "mixed"));

        $docblock->setTag($paramTag);

        $param = new ParameterGenerator("value");
        if ($type && !$type->isNativeType()) {
            $param->setType($type->getPhpType());
        }
        $method = new MethodGenerator("__construct", [
            $param
        ]);
        $method->setVisibility(MethodGenerator::VISIBILITY_PROTECTED);
        $method->setDocBlock($docblock);
        $method->setBody("\$this->value(\$value);");

        $generator->addMethodFromGenerator($method);
        
        //
        // public function __toString()
        //
        $docblock = new DocBlockGenerator('Gets a string value');
        $docblock->setTag(new ReturnTag("string"));
        $method = new MethodGenerator("__toString");
        $method->setDocBlock($docblock);
        $method->setBody("return strval(\$this->" . $prop->getName() . ");");
        $generator->addMethodFromGenerator($method);
        
        //
        // public function value($value = null)
        //
        $paramTag = new ParamTag("value", "mixed");
        $paramTag->setTypes($typeDefinition);
        
        $returnTag = new ReturnTag("mixed");
        $returnTag->setTypes($typeDefinition);        
        
        $docblock = new DocBlockGenerator('Gets or sets the inner value');        
        $docblock->setTag($paramTag);
        $docblock->setTag($returnTag);

        $param = new ParameterGenerator("value");
        $param->setDefaultValue(null);
        if ($type && !$type->isNativeType()) {
            $param->setType($type->getPhpType());
        }
        
        $methodBody  = "if (\$value !== null) {" . PHP_EOL;
        
        if ($checks = $this->getAvailableChecks( $prop, $class, 'whiteSpace' )) {
            foreach ($checks as $whiteSpace) {
                $methodBody .= "    \$this->value = RestrictionUtils::buildWhiteSpace(\$this->value, {$whiteSpace['value']});" . PHP_EOL;
            }
        } else {
            $methodBody .= "    \$this->__value = \$value;" . PHP_EOL;
        }
        
        $methodBody .= "}" . PHP_EOL;
        $methodBody .= "return \$this->__value;" . PHP_EOL;
        
        $method = new MethodGenerator("value", [
            $param
        ]);
        $method->setDocBlock($docblock);
        $method->setBody($methodBody);

        $generator->addMethodFromGenerator($method);
        
        //
        // public static function create($value)
        //
        $paramTag = new ParamTag("value", "mixed");
        $paramTag->setTypes($typeDefinition);
        
        $returnTag = new ReturnTag("mixed");
        $returnTag->setTypes($generator->getName());
        
        $docblock = new DocBlockGenerator("Helper to get a instance of the {$generator->getName()}");        
        $docblock->setTag($paramTag);
        $docblock->setTag($returnTag);

        $param = new ParameterGenerator("value");
        if ($type && !$type->isNativeType()) {
            $param->setType($type->getPhpType());
        }
        
        $method = new MethodGenerator("create", [
            $param
        ]);
        $method->setStatic(true);
        $method->setDocBlock($docblock);
        $method->setBody("return new static(\$value);");

        $generator->addMethodFromGenerator($method);         
        
        //
        // get method validate
        //
        $generator->getMethod('validate'); 
        
        //
        // Handle Check Restrictions
        //davidson
        $this->handleValueEnumeration( $generator, $prop, $class );
        $this->handleValuePattern( $generator, $prop, $class );
        $this->handleValueFractionDigits( $generator, $prop, $class );
        $this->handleValueTotalDigits( $generator, $prop, $class );        
        $this->handleValueLength( $generator, $prop, $class );
        $this->handleValueMaxLength( $generator, $prop, $class );
        $this->handleValueMinLength( $generator, $prop, $class );        
        $this->handleValueMaxExclusive( $generator, $prop, $class );
        $this->handleValueMinExclusive( $generator, $prop, $class );
        $this->handleValueMaxInclusive( $generator, $prop, $class );
        $this->handleValueMinInclusive( $generator, $prop, $class );
        
    }

    private function handleSetter(Generator\ClassGenerator $generator, PHPProperty $prop, PHPClass $class)
    {
        $methodBody = '';
        $docblock = new DocBlockGenerator();
        
        $docblock->setShortDescription("Sets a new " . $prop->getName());

        if ($prop->getDoc()) {
            $docblock->setLongDescription($prop->getDoc());
        }

        $patramTag = new ParamTag($prop->getName());
        $docblock->setTag($patramTag);

        $return = new ReturnTag("self");
        $docblock->setTag($return);

        $type = $prop->getType();

        $method = new MethodGenerator("set" . Inflector::classify($prop->getName()));

        $parameter = new ParameterGenerator($prop->getName(), "mixed");

        if ($type && $type instanceof PHPClassOf) {
            $patramTag->setTypes($type->getArg()
                    ->getType()->getPhpType() . "[]");
            $parameter->setType("array");

            if ($p = $type->getArg()->getType()->isSimpleType()) {
                if (($t = $p->getType())) {
                    $patramTag->setTypes($t->getPhpType()."[]");
                }
            }
        } elseif ($type) {
            if ($type->isNativeType()) {
                $patramTag->setTypes($type->getPhpType());
            } elseif ($p = $type->isSimpleType()) {
                if (($t = $p->getType()) && !$t->isNativeType()) {
                    $patramTag->setTypes($t->getPhpType());
                    $parameter->setType($t->getPhpType());
                } elseif ($t && !$t->isNativeType()) {
                    $patramTag->setTypes($t->getPhpType());
                    $parameter->setType($t->getPhpType());
                } elseif ($t) {
                    $patramTag->setTypes($t->getPhpType());
                }
            } else {
                $patramTag->setTypes($type->getPhpType());
                $parameter->setType($type->getPhpType());
            }
        }
       
        
        if ($type && $type->isSimpleType() && !!count($type->getChecks('__value'))) {
            $methodBody .= "\$this->" . $prop->getName() . " = " . $type->getPhpType() . "::create(\$" . $prop->getName() . ");" . PHP_EOL;
        } else if ($type && $type instanceof PHPClassOf && ($tt = $type->getArg()->getType()) && $tt->isSimpleType()) {
            $methodBody .= "\$this->" . $prop->getName() . " = array_map(function(\$value){". PHP_EOL;
            $methodBody .= "   return {$tt->getPhpType()}::create(\$value);" . PHP_EOL;
            $methodBody .= "}, \${$prop->getName()});" . PHP_EOL;
        } else {
            $methodBody .= "\$this->" . $prop->getName() . " = \$" . $prop->getName() . ";" . PHP_EOL;
        }
        $methodBody .= "return \$this;";
        $method->setBody($methodBody);
        $method->setDocBlock($docblock);
        $method->setParameter($parameter);

        $generator->addMethodFromGenerator($method);
    }

    private function handleGetter(Generator\ClassGenerator $generator, PHPProperty $prop, PHPClass $class)
    {

        if ($prop->getType() instanceof PHPClassOf) {
            $docblock = new DocBlockGenerator();
            $docblock->setShortDescription("isset " . $prop->getName());
            if ($prop->getDoc()) {
                $docblock->setLongDescription($prop->getDoc());
            }

            $patramTag = new ParamTag("index", "scalar");
            $docblock->setTag($patramTag);

            $docblock->setTag(new ReturnTag("boolean"));

            $paramIndex = new ParameterGenerator("index", "mixed");

            $method = new MethodGenerator("isset" . Inflector::classify($prop->getName()), [$paramIndex]);
            $method->setDocBlock($docblock);
            $method->setBody("return isset(\$this->" . $prop->getName() . "[\$index]);");
            $generator->addMethodFromGenerator($method);

            $docblock = new DocBlockGenerator();
            $docblock->setShortDescription("unset " . $prop->getName());
            if ($prop->getDoc()) {
                $docblock->setLongDescription($prop->getDoc());
            }

            $patramTag = new ParamTag("index", "scalar");
            $docblock->setTag($patramTag);
            $paramIndex = new ParameterGenerator("index", "mixed");

            $docblock->setTag(new ReturnTag("void"));


            $method = new MethodGenerator("unset" . Inflector::classify($prop->getName()), [$paramIndex]);
            $method->setDocBlock($docblock);
            $method->setBody("unset(\$this->" . $prop->getName() . "[\$index]);");
            $generator->addMethodFromGenerator($method);
        }
        // ////

        $docblock = new DocBlockGenerator();

        $docblock->setShortDescription("Gets as " . $prop->getName());

        if ($prop->getDoc()) {
            $docblock->setLongDescription($prop->getDoc());
        }

        $tag = new ReturnTag("mixed");
        $type = $prop->getType();
        if ($type && $type instanceof PHPClassOf) {
            $tt = $type->getArg()->getType();
            $tag->setTypes($tt->getPhpType() . "[]");
            if ($p = $tt->isSimpleType()) {
                if (!!count($tt->getChecks('__value'))) {
                    $tag->setTypes($tt->getPhpType() . "[]");
                } else
                if (($t = $p->getType())) {
                    $tag->setTypes($t->getPhpType() . "[]");
                }
            }
        } elseif ($type) {

            if ($p = $type->isSimpleType()) {
                if (!!count($type->getChecks('__value'))) {
                    $tag->setTypes($type->getPhpType());
                } else
                if ($t = $p->getType()) {
                    $tag->setTypes($t->getPhpType());
                }
            } else {
                $tag->setTypes($type->getPhpType());
            }
        }

        $docblock->setTag($tag);

        $method = new MethodGenerator("get" . Inflector::classify($prop->getName()));
        $method->setDocBlock($docblock);
        $method->setBody("return \$this->" . $prop->getName() . ";");

        $generator->addMethodFromGenerator($method);
    }

    private function handleAdder(Generator\ClassGenerator $generator, PHPProperty $prop, PHPClass $class)
    {
        $type = $prop->getType();
        $propName = $type->getArg()->getName();

        $docblock = new DocBlockGenerator();
        $docblock->setShortDescription("Adds as $propName");

        if ($prop->getDoc()) {
            $docblock->setLongDescription($prop->getDoc());
        }

        $return = new ReturnTag();
        $return->setTypes("self");
        $docblock->setTag($return);

        $patramTag = new ParamTag($propName, $type->getArg()->getType()->getPhpType());
        $docblock->setTag($patramTag);

        $method = new MethodGenerator("addTo" . Inflector::classify($prop->getName()));

        $parameter = new ParameterGenerator($propName);
        $tt = $type->getArg()->getType();

        if (!$tt->isNativeType()) {

            if ($p = $tt->isSimpleType()) {
                if (($t = $p->getType())) {
                    $patramTag->setTypes($t->getPhpType());

                    if (!$t->isNativeType()) {
                        $parameter->setType($t->getPhpType());
                    }
                }
            } elseif (!$tt->isNativeType()) {
                $parameter->setType($tt->getPhpType());
            }
        }
        
        if ($tt->isSimpleType() && !!count($tt->getChecks('__value'))) {
            $methodBody = "\$this->" . $prop->getName() . "[] = {$tt->getPhpType()}::create(\$" . $propName . ");" . PHP_EOL;
        } else {
            $methodBody = "\$this->" . $prop->getName() . "[] = \$" . $propName . ";" . PHP_EOL;
        }
        $methodBody .= "return \$this;";
        $method->setBody($methodBody);
        $method->setDocBlock($docblock);
        $method->setParameter($parameter);

        $generator->addMethodFromGenerator($method);
    }

    private function handleMethod(Generator\ClassGenerator $generator, PHPProperty $prop, PHPClass $class)
    {
        if ($prop->getType() instanceof PHPClassOf) {
            $this->handleAdder($generator, $prop, $class);
        }

        $this->handleGetter($generator, $prop, $class);
        $this->handleSetter($generator, $prop, $class);
        
    }

    protected function handleValidateMethod(Generator\ClassGenerator $class) {
        
        $methodValidate = $class->getMethod('validate');
        if (!$methodValidate) {
            
            $class->addUse('GoetasWebservices\XML\XSDReader\Utils\RestrictionUtils');
            $class->addUse('GoetasWebservices\XML\XSDReader\Exception\RestrictionException');
            
            $returnTag = new ReturnTag("array");

            $docblock = new DocBlockGenerator('Validate value');
            $docblock->setTag($returnTag);

            $methodValidate = new MethodGenerator("validate");
            $methodValidate->setVisibility(MethodGenerator::VISIBILITY_PUBLIC);
            $methodValidate->setDocBlock($docblock);

            $class->addMethodFromGenerator($methodValidate); 
        }
        
    }
    
    private function handleProperty(Generator\ClassGenerator $class, PHPProperty $prop)
    {        
        $type = $prop->getType();
        
        //
        // protected function validate($value)
        //
        $methodValidate = $class->getMethod('validate');
        
        $min = $prop->getMin();
        $max = $prop->getMax();
        
        if ($type && $type instanceof PHPClassOf && ($tt = $type->getArg()) instanceof PHPProperty) {
            $min = $tt->getMin();
            $max = $tt->getMax();
        }
                
        $body = $methodValidate->getBody();
        if ($min > 0) {
            $body .= "RestrictionUtils::checkRequired(\$this->{$prop->getName()}, {$min}, {$max});" . PHP_EOL;
        }
        
        if ($type && $type instanceof PHPClassOf && ($tt = $type->getArg()->getType()) && !$tt->isSimpleType() && !$tt->isNativeType()) {
            
            $body .= "foreach (\$this->{$prop->getName()} as \$value) {" . PHP_EOL;;
            $body .= "    \$value->validate();" . PHP_EOL;
            $body .= "}" . PHP_EOL;
            
        } else
        if ($type && $type instanceof PHPClassOf && ($tt = $type->getArg()) instanceof PHPProperty) {
            
            $body .= "foreach (\$this->{$prop->getName()} as \$value) {" . PHP_EOL;;
            if (!$tt->getType()->isNativeType()) {
                $body .= "    \$value->validate();" . PHP_EOL;
            } else {
                $body .= "    RestrictionUtils::checkRequired(\$value, {$prop->getMin()}, {$prop->getMax()});" . PHP_EOL;
            }
            $body .= "}" . PHP_EOL;
        }
                
        if ($type && !$type->isNativeType()) {//$type->isSimpleType() && !!count($type->getChecks('__value'))
            $body .= '$this->' . $prop->getName() . '->validate();' . PHP_EOL;
        } 
        $methodValidate->setBody( $body );
        
        $generatedProp = new PropertyGenerator($prop->getName());
        $generatedProp->setVisibility(PropertyGenerator::VISIBILITY_PRIVATE);

        $class->addPropertyFromGenerator($generatedProp);

        if ($prop->getType() && (!$prop->getType()->getNamespace() && $prop->getType()->getName() == "array")) {
            // $generatedProp->setDefaultValue(array(), PropertyValueGenerator::TYPE_AUTO, PropertyValueGenerator::OUTPUT_SINGLE_LINE);
        }

        $docBlock = new DocBlockGenerator();
        $generatedProp->setDocBlock($docBlock);

        if ($prop->getDoc()) {
            $docBlock->setLongDescription($prop->getDoc());
        }
        $tag = new PropertyTag($prop->getName(), 'mixed');

        if ($type && $type instanceof PHPClassOf) {
            $tt = $type->getArg()->getType();
            $tag->setTypes($tt->getPhpType() . "[]");
            if ($p = $tt->isSimpleType()) {
                if (($t = $p->getType())) {
                    $tag->setTypes($t->getPhpType() . "[]");
                }
            }
            
        } elseif ($type) {

            if ($type->isNativeType()) {
                $tag->setTypes($type->getPhpType());                
            } elseif (($p = $type->isSimpleType()) && !!count($type->getChecks('__value'))) {
                $tag->setTypes($type->getPhpType());
            } elseif ($p && ($t = $p->getType())) {
                $tag->setTypes($t->getPhpType());
            } else {
                $tag->setTypes($prop->getType()->getPhpType());
            }
        }
        $docBlock->setTag($tag);
    }

    public function generate(PHPClass $type)
    {
        $class = new \Zend\Code\Generator\ClassGenerator();
        $docblock = new DocBlockGenerator("Class representing " . $type->getName());
        if ($type->getDoc()) {
            $docblock->setLongDescription($type->getDoc());
        }
        $class->setNamespaceName($type->getNamespace() ?: NULL);
        $class->setName($type->getName());
        $class->setDocblock($docblock);

        $this->handleValidateMethod($class);
        
        if ($extends = $type->getExtends()) {
            if ($p = $extends->isSimpleType()) {
                $this->handleProperty($class, $p);
                $this->handleValueMethod($class, $p, $type);
            } else {

                $class->setExtendedClass($extends->getName());

                if ($extends->getNamespace() != $type->getNamespace()) {
                    if ($extends->getName() == $type->getName()) {
                        $class->addUse($type->getExtends()
                            ->getFullName(), $extends->getName() . "Base");
                        $class->setExtendedClass($extends->getName() . "Base");
                    } else {
                        $class->addUse($extends->getFullName());
                    }
                }
            }
        }

        if ($this->handleBody($class, $type)) {
            return $class;
        }
    }
}
