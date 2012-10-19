<?php
// Include the Base Class (only once)
require_once('elementadapter.class.php');

class SnippetAdapter extends ElementAdapter
{
    protected $dbClass = 'modSnippet';
    protected $dbClassIDKey = 'name';
    protected $dbClassNameKey = 'name';
    protected $dbClassParentKey = 'category';
    protected $createProcessor = 'element/snippet/create';
    protected $updateProcessor = 'element/snippet/update';
    
// Database Columns for the XPDO Object
    protected $myFields;


    final public function __construct(&$modx, &$helpers, $fields, $mode = MODE_BOOTSTRAP) {
        $this->name = $fields['name'];
        parent::__construct($modx, $helpers, $fields, $mode);
    }
    
/* *****************************************************************************
   Bootstrap and Support Functions (in ElementAdapter)
***************************************************************************** */

/* *****************************************************************************
   Import Objects and Support Functions (in ElementAdapter) 
***************************************************************************** */

/* *****************************************************************************
   Export Objects and Support Functions (in ElementAdapter)
***************************************************************************** */

/* *****************************************************************************
   Build Vehicle and Support Functions 
***************************************************************************** */
    final public function buildVehicle()
    {//Add to the Transport Package
        if (parent::buildVehicle())
        {//Return Success
            $myComponent->log(modX::LOG_LEVEL_INFO, 'Packaged Resource: '.$this->properties['pagetitle']);
            return true;
        }
    }
}