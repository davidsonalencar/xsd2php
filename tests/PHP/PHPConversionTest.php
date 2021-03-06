<?php
namespace GoetasWebservices\Xsd\XsdToPhp\Tests\JmsSerializer\OTA;

use GoetasWebservices\Xsd\XsdToPhp\Naming\ShortNamingStrategy;
use GoetasWebservices\Xsd\XsdToPhp\Php\ClassGenerator;
use GoetasWebservices\Xsd\XsdToPhp\Php\PhpConverter;
use GoetasWebservices\XML\XSDReader\SchemaReader;

class PHPConversionTest extends \PHPUnit_Framework_TestCase
{

    /**
     *
     * @param mixed $xml
     * @return \Zend\Code\Generator\ClassGenerator[]
     */
    protected function getClasses($xml)
    {
        $phpcreator = new PhpConverter(new ShortNamingStrategy());
        $phpcreator->addNamespace('http://www.example.com', 'Example');

        $generator = new ClassGenerator();
        $reader = new SchemaReader();

        if (!is_array($xml)) {
            $xml = [
                'schema.xsd' => $xml
            ];
        }
        $schemas = [];
        foreach ($xml as $name => $str) {
            $schemas[] = $reader->readString($str, $name);
        }
        $items = $phpcreator->convert($schemas);
        
        $classes = array();
        foreach ($items as $k => $item) {
            if ($codegen = $generator->generate($item)) {
                $classes[$k] = $codegen;
            }
        }
        
        return $classes;
    }

//    public function testSimpleContent()
//    {
//        $xml = '
//            <xs:schema targetNamespace="http://www.example.com"
//            xmlns:xs="http://www.w3.org/2001/XMLSchema">
//                <xs:complexType name="single">
//                    <xs:simpleContent>
//    					<xs:extension base="xs:string">
//    						<xs:attribute name="code" type="xs:string"/>
//    					</xs:extension>
//				    </xs:simpleContent>
//                </xs:complexType>
//            </xs:schema>';
//
//        $items = $this->getClasses($xml);
//        $this->assertCount(1, $items);
//
//        $codegen = $items['Example\SingleType'];
//
//        $this->assertTrue($codegen->hasMethod('value'));
//        $this->assertTrue($codegen->hasMethod('__construct'));
//        $this->assertTrue($codegen->hasMethod('__toString'));
//
//        $this->assertTrue($codegen->hasMethod('getCode'));
//        $this->assertTrue($codegen->hasMethod('setCode'));
//    }
//
//    public function testSimpleNoAttributesContent()
//    {
//        $xml = '
//            <xs:schema targetNamespace="http://www.example.com"
//            xmlns:xs="http://www.w3.org/2001/XMLSchema">
//                <xs:complexType name="single">
//                    <xs:simpleContent>
//    					<xs:extension base="xs:string"/>
//				    </xs:simpleContent>
//                </xs:complexType>
//                <xs:simpleType name="double">
//                    <xs:restriction base="xs:string"/>
//                </xs:simpleType>
//            </xs:schema>';
//
//        $items = $this->getClasses($xml);
//        $this->assertCount(1, $items);
//
//        $codegen = $items['Example\SingleType'];
//
//        $this->assertTrue($codegen->hasMethod('value'));
//        $this->assertTrue($codegen->hasMethod('__construct'));
//        $this->assertTrue($codegen->hasMethod('__toString'));
//    }
//
//
//    public function testNoMulteplicity()
//    {
//        $xml = '
//            <xs:schema targetNamespace="http://www.example.com"
//            xmlns:xs="http://www.w3.org/2001/XMLSchema">
//                <xs:complexType name="single">
//                    <xs:all>
//                        <xs:element name="id" type="xs:long" minOccurs="0"/>
//                    </xs:all>
//                </xs:complexType>
//            </xs:schema>';
//
//        $items = $this->getClasses($xml);
//        $this->assertCount(1, $items);
//
//        $codegen = $items['Example\SingleType'];
//        $this->assertFalse($codegen->hasMethod('issetId'));
//        $this->assertFalse($codegen->hasMethod('unsetId'));
//
//        $this->assertTrue($codegen->hasMethod('getId'));
//        $this->assertTrue($codegen->hasMethod('setId'));
//    }
//
//    public function testMulteplicity()
//    {
//        $xml = '
//            <xs:schema targetNamespace="http://www.example.com"
//            xmlns:xs="http://www.w3.org/2001/XMLSchema">
//                <xs:complexType name="single">
//                    <xs:all>
//                        <xs:element name="id" type="ary" minOccurs="0"/>
//                    </xs:all>
//                </xs:complexType>
//                <xs:complexType name="ary">
//                    <xs:all>
//                        <xs:element name="id" type="xs:long" maxOccurs="2"/>
//                    </xs:all>
//                </xs:complexType>
//            </xs:schema>';
//
//        $items = $this->getClasses($xml);
//
//        $this->assertCount(1, $items);
//
//        $codegen = $items['Example\SingleType'];
//        $this->assertTrue($codegen->hasMethod('issetId'));
//        $this->assertTrue($codegen->hasMethod('unsetId'));
//
//        $this->assertTrue($codegen->hasMethod('getId'));
//        $this->assertTrue($codegen->hasMethod('setId'));
//    }
//
//    public function testNestedMulteplicity()
//    {
//        $xml = '
//            <xs:schema targetNamespace="http://www.example.com" xmlns:xs="http://www.w3.org/2001/XMLSchema">
//                <xs:complexType name="single">
//                    <xs:all>
//                        <xs:element name="id" type="ary" minOccurs="0"/>
//                    </xs:all>
//                </xs:complexType>
//                <xs:complexType name="ary">
//                    <xs:all>
//                        <xs:element name="idA" type="ary2" maxOccurs="2"/>
//                    </xs:all>
//                </xs:complexType>
//                <xs:complexType name="ary2">
//                    <xs:all>
//                        <xs:element name="idB" type="xs:long" maxOccurs="2"/>
//                    </xs:all>
//                </xs:complexType>
//            </xs:schema>';
//
//        $items = $this->getClasses($xml);
//
//        $this->assertCount(2, $items);
//
//        $single = $items['Example\SingleType'];
//        $this->assertTrue($single->hasMethod('issetId'));
//        $this->assertTrue($single->hasMethod('unsetId'));
//
//        $this->assertTrue($single->hasMethod('getId'));
//        $this->assertTrue($single->hasMethod('setId'));
//
//        $ary = $items['Example\Ary2Type'];
//        $this->assertTrue($ary->hasMethod('issetIdB'));
//        $this->assertTrue($ary->hasMethod('unsetIdB'));
//
//        $this->assertTrue($ary->hasMethod('getIdB'));
//        $this->assertTrue($ary->hasMethod('setIdB'));
//    }
//
//    public function testMultipleArrayTypes()
//    {
//        $xml = '
//            <xs:schema targetNamespace="http://www.example.com"
//            xmlns:xs="http://www.w3.org/2001/XMLSchema">
//
//                <xs:complexType name="ArrayOfStrings">
//                    <xs:all>
//                        <xs:element name="string" type="xs:string" maxOccurs="unbounded"/>
//                    </xs:all>
//                </xs:complexType>
//
//                <xs:complexType name="Single">
//                    <xs:all>
//                        <xs:element name="a" type="ArrayOfStrings"/>
//                        <xs:element name="b" type="ArrayOfStrings"/>
//                    </xs:all>
//                </xs:complexType>
//
//            </xs:schema>';
//
//        $items = $this->getClasses($xml);
//
//        $this->assertCount(1, $items);
//
//        $single = $items['Example\SingleType'];
//        $this->assertTrue($single->hasMethod('addToA'));
//        $this->assertTrue($single->hasMethod('addToB'));
//
//    }
//
//    public function testSimpleMulteplicity()
//    {
//        $xml = '
//            <xs:schema targetNamespace="http://www.example.com"
//            xmlns:xs="http://www.w3.org/2001/XMLSchema">
//                <xs:complexType name="single">
//                    <xs:all>
//                        <xs:element name="id" type="ary" minOccurs="0"/>
//                    </xs:all>
//                </xs:complexType>
//                <xs:simpleType name="ary">
//                    <xs:list itemType="xs:integer" />
//                </xs:simpleType>
//            </xs:schema>';
//
//        $items = $this->getClasses($xml);
//
//        $this->assertCount(1, $items);
//
//        $single = $items['Example\SingleType'];
//        $this->assertTrue($single->hasMethod('issetId'));
//        $this->assertTrue($single->hasMethod('unsetId'));
//
//        $this->assertTrue($single->hasMethod('getId'));
//        $this->assertTrue($single->hasMethod('setId'));
//    }
    
    public function testSimpleTypeWithPattern()
    {
        /*
        $xml = '
            <xs:schema targetNamespace="http://www.example.com"
            xmlns:xs="http://www.w3.org/2001/XMLSchema">
                <xs:simpleType name="dm_motivoSaida">
                    <xs:restriction base="xs:decimal">
                        <xs:totalDigits value="3"/>
			<xs:fractionDigits value="2"/>
                        <xs:length value="1"/>
                    </xs:restriction>
                </xs:simpleType>
            </xs:schema>';
         */
        
        $xml = '
            <xs:schema targetNamespace="http://www.example.com"
            xmlns:xs="http://www.w3.org/2001/XMLSchema">
                <xs:complexType name="cabecalhoTransacao">
                    <xs:sequence>
			<xs:element name="identificacaoTransacao" type="xs:string"/>
			<xs:element name="falhaNegocio" type="xs:string" minOccurs="0"/>
                        <xs:element name="motivo" type="dm_motivoSaida"/>
                        <xs:element name="procedimentosRealizados">
                            <xs:complexType>
                                <xs:sequence>
                                    <xs:element name="procedimentoRealizado" type="xs:string" maxOccurs="unbounded"/>
                                </xs:sequence>
                            </xs:complexType>
			</xs:element>
                        <xs:element name="relacaoGuias" minOccurs="0" maxOccurs="unbounded">
                            <xs:complexType>
                                <xs:sequence>
                                    <xs:element name="numeroGuiaPrestador" type="st_texto4"/>
                                    <xs:element name="numeroGuiaOperadora" type="st_texto4" minOccurs="0"/>
                                </xs:sequence>
                            </xs:complexType>
                        </xs:element>
                    </xs:sequence>
                </xs:complexType>
                <xs:simpleType name="dm_motivoSaida">
                    <xs:restriction base="xs:decimal">
                        <xs:length value="1"/>
                        <xs:whiteSpace value="replace"/>
                        <xs:totalDigits value="3"/>
			<xs:fractionDigits value="2"/>
                    </xs:restriction>
                </xs:simpleType>
                <xs:simpleType name="st_texto4">
                    <xs:restriction base="xs:string">
                        <xs:minLength value="1"/>
                        <xs:maxLength value="4"/>
                    </xs:restriction>
                </xs:simpleType>
            </xs:schema>';

        $items = $this->getClasses($xml);

        //$this->assertCount(2, $items);
        
        $single = $items['Example\CabecalhoTransacaoType'];
        
            $fileGen = new \Zend\Code\Generator\FileGenerator();
            $fileGen->setFilename('CabecalhoTransacaoType.php');
            $fileGen->setClass($single);
            $fileGen->write();     
        
        $single = $items['Example\DmMotivoSaidaType'];
            
            $fileGen = new \Zend\Code\Generator\FileGenerator();
            $fileGen->setFilename('DmMotivoSaidaType.php');
            $fileGen->setClass($single);
            $fileGen->write();    
            
            
        $single = $items['Example\StTexto4Type'];
            
            $fileGen = new \Zend\Code\Generator\FileGenerator();
            $fileGen->setFilename('StTexto4Type.php');
            $fileGen->setClass($single);
            $fileGen->write();    
            
        $single = $items['Example\CabecalhoTransacaoType\RelacaoGuiasAType'];
            
            $fileGen = new \Zend\Code\Generator\FileGenerator();
            $fileGen->setFilename('RelacaoGuiasAType.php');
            $fileGen->setClass($single);
            $fileGen->write();    
            
        die();
//        
//        $file = new \GoetasWebservices\Xsd\XsdToPhp\Writer\PHPClassWriter($pathGen);
//        $file->write($items);
        //var_dump($file);
        
        die();
//        $fileGen = new FileGenerator();
//        $fileGen->setFilename($path);
//        $fileGen->setClass($item);
//        $fileGen->write();
        
        $this->assertTrue($single->hasMethod('issetId'));
        $this->assertTrue($single->hasMethod('unsetId'));

        $this->assertTrue($single->hasMethod('getId'));
        $this->assertTrue($single->hasMethod('setId'));
    }
}
