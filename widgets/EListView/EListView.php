<?php
  
  Yii::import('zii.widgets.CListView');

  class EListView extends CListView
  {
    //Configurable PARAMS ------------------------------------------------------------------------------------------
    //--------------------------------------------------------------------------------------------------------------
    public $itemsPerPageMenu                 = array(); // [10,20,50,100,200,500]
    public $itemsPerPageMenuClass            = "ippm";  // Class name to aply to Item Per Page PullDown Menu 
    public $itemsPerPageMenuItemOptionPrefix = "";      // PREFIX X SUFIX ie VIEW 10 Per Page
    public $itemsPerPageMenuItemOptionSufix  = "";      // PREFIX X SUFIX ie VIEW 20 Per Page
    public $scrollToItem                     = array(
                                                  'on'              =>'MISMATCH' // ['ALWAYS', 'MISTACH', 'NEVER']
                                                , 'containerEntity' =>'body'     // DOM ELEMENT TO SCROLL
                                                , 'itemClass'       =>'view'     // THE CLASS NAME OF ITEMS IN VIEW
                                              );
    public $renderMenuInAltDomElmWithID      = false;   //The Div To Render The Item Per Page PullDown Menu In
    //--------------------------------------------------------------------------------------------------------------
    //--------------------------------------------------------------------------------------------------------------

    public $pageSize                         = null; //Internal Use To Track Items per page 
    public $start                            = null; //Internal Use To Track The Start Item.


    public function init()
    {
      $this->calcStartPage();
      parent::init();
    }
    
    /**
     * Calculates the new start page
     * Sets $_GET[$pageVar] & $this->start & $this->pageSize & $session[$pageVar]
     */ 
    private function calcStartPage()
    {
      $session=new CHttpSession;
      $session->open();

      if(!isset($_GET[($this->dataProvider->getPagination()->pageVar)]))
        $_GET[($this->dataProvider->getPagination()->pageVar)] = 1;

      if((isset($session[($this->dataProvider->getPagination()->pageVar)])))
        $origIPP = $session[($this->dataProvider->getPagination()->pageVar)];
      else
        $origIPP =  $this->dataProvider->getPagination()->pageSize;

      $this->pageSize                 = (isset($_GET['pageSize']))? $_GET['pageSize'] : $origIPP;
      $pageVar                        = $this->dataProvider->getPagination()->pageVar;
      $curPage                        = (isset($_GET[$pageVar]))? $_GET[$pageVar] : 1;
      $start                          = (($curPage -1)*$origIPP+1);
      $this->start                    = $start;
      $_GET[$pageVar]                 = (intval($start/$this->pageSize)+1);
      $this->dataProvider->pagination = CMap::mergeArray(array(
                                          'pageSize'=>$this->pageSize)
                                         , $this->dataProvider->getPagination()
                                        );
      $session[$pageVar]              = $this->pageSize;
      
      $session->close();
      return $_GET[$pageVar];
    }
    
    /**
     * Overrides the CListView->renderSummary() 
     * Icludes IPP Menu {itemsPerPage} and Additonal JS
     */ 
    public function renderSummary()
    {
      if(($count=$this->dataProvider->getItemCount())<=0)
        return;

      echo '<div class="'.$this->summaryCssClass.'">';
      if($this->enablePagination)
      {
        if(($summaryText=$this->summaryText)===null)
          $summaryText=Yii::t('zii','Displaying {start}-{end} of {count} result(s). {itemsPerPage}');

        $pagination = $this->dataProvider->getPagination();
        $total      = $this->dataProvider->getTotalItemCount();
        $start      = $pagination->currentPage*$pagination->pageSize+1;
        $end        = $start+$count-1;
  
        $summaryText .= $this->createJS();
        if($end>$total)
        {
          $end=$total;
          $start=$end-$count+1;
        }
        echo strtr($summaryText,array(
          '{start}'        =>$start,
          '{end}'          =>$end,
          '{count}'        =>$total,
          '{page}'         =>$pagination->currentPage+1,
          '{pages}'        =>$pagination->pageCount,
          '{itemsPerPage}' =>$this->createItemsPerPageMenu(),
        ));
      }
      else
      {
        if(($summaryText=$this->summaryText)===null)
          $summaryText=Yii::t('zii','Total {count} result(s).');
        echo strtr($summaryText,array(
          '{count}'        =>$count,
          '{start}'        =>1,
          '{end}'          =>$count,
          '{page}'         =>1,
          '{pages}'        =>1,
          '{itemsPerPage}' =>$this->createItemsPerPageMenu(),
        ));
      }
      echo '</div>';
    }
    
    /**
     * Creates Items Per Page Menu
     */ 
    private function createItemsPerPageMenu()
    {
      $ippm = "";

      $session=new CHttpSession;
      $session->open();

      if((isset($session[($this->dataProvider->getPagination()->pageVar)])))
      {
        if($this->dataProvider->getPagination()->pageSize == $session[($this->dataProvider->getPagination()->pageVar)])
          $curPage = $session[($this->dataProvider->getPagination()->pageVar)];
        else
          $curPage =  $this->dataProvider->getPagination()->pageSize;
      }
      else
        $curPage =  $this->dataProvider->getPagination()->pageSize;
      
      $session->close();

      if(count($this->itemsPerPageMenu))
      {
        $ippm .= "<span id=\"ippmID\"><select class=\"" . $this->itemsPerPageMenuClass . "\" name=\"" . $this->itemsPerPageMenuClass . "\" onChange=\"document.location='index?" .  $this->dataProvider->getPagination()->pageVar . "=" . $_GET[($this->dataProvider->getPagination()->pageVar)] . "&pageSize='+this.value\">";
        for ($i = 0; $i < count($this->itemsPerPageMenu); $i++) {
           $ippm .= "\n\t<option value=\"" . $this->itemsPerPageMenu[$i] . "\" " . ( ($curPage == $this->itemsPerPageMenu[$i])? "SELECTED" : '' ) . ">" . $this->itemsPerPageMenuItemOptionPrefix . " " . $this->itemsPerPageMenu[$i] . " " . $this->itemsPerPageMenuItemOptionSufix . "</option>";
        }
        $ippm .= "</select></span>";
      }
      return $ippm;
    }


    //JS HELPER FUNCTIONS-------------------------------------------------------------------------------------------
    //--------------------------------------------------------------------------------------------------------------
    
    /**
     * Returns JS To Execute On Page Load
     */ 
    private function createJS()
    {
      $js  = CHtml::openTag('script');
      $js .= $this->createJSScrollOffset($this->offsetPoint());
      $js .= $this->createJSRenderAltMenuLoc();
      $js .= $this->jsOnLoadFunctionCalls($this->offsetPoint());
      $js .= CHtml::closeTag('script');

      return $js;
    }

     /**
     * Returns The Offset In Pixels To The Top Item Of From Previous State
     * Used By JS To Scroll To Previous Position 
     */ 
    private function offsetPoint()
    {
      if(is_null($this->start))
        return 0;
      else
        return abs(($this->pageSize * ( $_GET[($this->dataProvider->getPagination()->pageVar)] -1 )) - ($this->start -1));
    }
    
    /**
     * Returns Functions To Call On Load
     */ 
    private function jsOnLoadFunctionCalls($offsetPoint)
    {
      $js = '$(document).ready(function() {' . "\n";
      if(!$this->scrollToItem || (isset($this->scrollToItem['on']) && $this->scrollToItem['on'] != 'NEVER'))
      {  
        if($this->scrollToItem['on'] == 'ALWAYS' || ($this->scrollToItem['on'] == 'MISMATCH' && $offsetPoint != 0))
          $js .= 'scrollToOffset();' . "\n";
      }
      
      if($this->renderMenuInAltDomElmWithID)
        $js .= 'renderAltMenuLoc("' . $this->renderMenuInAltDomElmWithID . '")' . "\n";
        
      $js .= $this->createJSPushState() . "\n";
      $js .=  '});' . "\n";

      return $js;
    }
    
    /**
     * Returns JS Function That Auto Scrolls Page
     */ 
    private function createJSScrollOffset($offsetPoint)
    {
      return '
        function scrollToOffset() {
          var container = $("' . $this->scrollToItem['containerEntity'] . '"), scrollTo =  $(".' . $this->scrollToItem['itemClass'] . '")[' . $offsetPoint . '];
          container.animate({
            scrollTop: $(scrollTo).offset().top - container.offset().top + container.scrollTop()
          });
        }';
    }
    
    /**
     * Returns JS Function To Render IPP Menu In Alternative DIV
     */ 
    private function createJSRenderAltMenuLoc()
    {
      return '
        function renderAltMenuLoc(id)
        {
          if(id)
          {
            $("#ippmID").hide();
            $("#"+id).get(0).innerHTML = $("#ippmID").get(0).innerHTML;
          }
        }
      ';
    }
    
    /**
     * Returns JS To Modify URL After $_GET Vars have been modified
     */ 
    private function createJSPushState()
    {
      return 'if(window.history && window.history.pushState)
              window.history.pushState("","","?' . $this->dataProvider->getPagination()->pageVar . '=' . $_GET[($this->dataProvider->getPagination()->pageVar)] . '&pageSize=' . $this->pageSize . '");';
      
    }
    //--------------------------------------------------------------------------------------------------------------
    //--------------------------------------------------------------------------------------------------------------

  }

