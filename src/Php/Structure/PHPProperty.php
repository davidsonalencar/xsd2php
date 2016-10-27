<?php
namespace GoetasWebservices\Xsd\XsdToPhp\Php\Structure;

class PHPProperty extends PHPArg
{

    /**
     * @var string
     */
    protected $visibility = 'protected';
    
    /**
     * @var int
     */
    protected $min = 0;
    
    /**
     * @var int
     */
    protected $max = 1;

    /**
     * @return string
     */
    public function getVisibility()
    {
        return $this->visibility;
    }

    /**
     * @param string $visibility
     * @return $this
     */
    public function setVisibility($visibility)
    {
        $this->visibility = $visibility;
        return $this;
    }
    
    /**
     * @param int $max
     * @return $this
     */
    public function setMax($max)
    {
        $this->max = $max;
        return $this;
    }
    
    /**
     * @param int $min
     * @return $this
     */
    public function setMin($min)
    {
        $this->min = $min;
        return $this;
    }
    
    /**
     * @return int
     */
    public function getMax()
    {
        return $this->max;
    }
    
    /**
     * @return int
     */
    public function getMin()
    {
        return $this->min;
    }
}
