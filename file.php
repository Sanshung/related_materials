<?php

class RelatedMaterials
{
    function __construct()
    {
        \Bitrix\Main\Loader::includeModule('iblock');
    }

    public static $minCount = 3; //минимальное совпадение по тэгам

    public static function GetListTags($filter = array())
    {
        \Bitrix\Main\Loader::includeModule('iblock');
        $arResult = array();
        $filter['IBLOCK_ID'] = 13;
        $ob = CIBlockElement::GetList(array(), $filter);
        while ($ar = $ob->Fetch()) {
            $arResult[$ar['ID']] = $ar['ID'];
        }
        return $arResult;
    }

    public static function GetListElement($tagsID = 0)
    {
        $arResult = array();
        $arIb = array(1, 157, 162); // новости, статьи, консультации

        if(is_array($tagsID))
        {
            foreach ($tagsID as $tagID)
            {
                $arFilter = array('IBLOCK_ID' => $arIb, 'ACTIVE' => 'Y');
                $arFilter['PROPERTY_TAGS'] = $tagID;

                $ob = CIBlockElement::GetList(array(), $arFilter, false, false, array('PROPERTY_TAGS', 'ID', 'IBLOCK_ID'));
                while ($ar = $ob->GetNext()) {
                    if ($ar['PROPERTY_TAGS_VALUE'] > 0) {
                        $arResult[$ar['ID']][] = $ar['PROPERTY_TAGS_VALUE'];
                    }
                }
            }
        }
        else
        {
            $arFilter = array('IBLOCK_ID' => $arIb, 'ACTIVE' => 'Y');
            $ob = CIBlockElement::GetList(array(), $arFilter, false, false, array('PROPERTY_TAGS', 'ID', 'IBLOCK_ID'));
            while ($ar = $ob->GetNext()) {
                if ($ar['PROPERTY_TAGS_VALUE'] > 0) {
                    $arResult[$ar['ID']][] = $ar['PROPERTY_TAGS_VALUE'];
                }
            }
        }

        foreach ($arResult as $key => $item) {
            if (count($item) < self::$minCount) {
                unset($arResult[$key]);
            }
        }
        return $arResult;
    }

    public static function GetListSection($tagID = 0)
    {
        $arResult = array();
        $arFilter = array('IBLOCK_ID' => 93, 'SECTION_ID' => 17420);
        if($tagID > 0)
            $arFilter['UF_TAGS_ELEMENT'] = $tagID;
        $ob = CIBlockSection::GetList(array(), $arFilter, false, array('ID', 'UF_TAGS_ELEMENT'));
        while ($ar = $ob->GetNext()) {
            if (!empty($ar['UF_TAGS_ELEMENT']) && count($ar['UF_TAGS_ELEMENT']) >= self::$minCount) {
                $arResult['S' . $ar['ID']] = $ar['UF_TAGS_ELEMENT'];
            }
        }
        return $arResult;
    }

    public static function MaterialsGetList()
    {
        $arResult = array();
        $ob = CIBlockElement::GetList(array(), array('IBLOCK_ID' => 192), false, false, array('PROPERTY_TAGS', 'PROPERTY_ELEMENT', 'PROPERTY_SECTION', 'PROPERTY_ELEMENT2', 'PROPERTY_SECTION2', 'ID', 'IBLOCK_ID'));
        while ($ar = $ob->GetNext()) {
            if (!empty($ar['PROPERTY_SECTION2_VALUE']) && !empty($ar['PROPERTY_SECTION_VALUE'])) {
                $arResult['SECTION'][$ar['PROPERTY_SECTION_VALUE']]['S'][$ar['PROPERTY_SECTION2_VALUE']] = 1;
            } elseif (!empty($ar['PROPERTY_SECTION_VALUE']) && !empty($ar['PROPERTY_ELEMENT2_VALUE'])) {
                $arResult['SECTION'][$ar['PROPERTY_SECTION_VALUE']]['E'][$ar['PROPERTY_ELEMENT2_VALUE']] = 1;
            } elseif (!empty($ar['PROPERTY_ELEMENT_VALUE']) && !empty($ar['PROPERTY_SECTION2_VALUE'])) {
                $arResult['ELEMENT'][$ar['PROPERTY_ELEMENT_VALUE']]['S'][$ar['PROPERTY_SECTION2_VALUE']] = 1;
            } elseif (!empty($ar['PROPERTY_ELEMENT_VALUE']) && !empty($ar['PROPERTY_ELEMENT2_VALUE'])) {
                $arResult['ELEMENT'][$ar['PROPERTY_ELEMENT_VALUE']]['E'][$ar['PROPERTY_ELEMENT2_VALUE']] = 1;
            }


        }
        return $arResult;
    }
    public static function Generate($elementID = 0, $sectionID = 0)
    {
        ini_set('max_execution_time', 120);
        set_time_limit(120);
        \Bitrix\Main\Loader::includeModule('iblock');
        if($elementID > 0) // находим используемые тэги
        {
            $arIb = array(1, 157, 162); // новости, статьи, консультации
            $arFilter = array('IBLOCK_ID' => $arIb, 'ACTIVE' => 'Y', 'ID' => $elementID);
            $ob = CIBlockElement::GetList(array(), $arFilter, false, false, array('PROPERTY_TAGS', 'ID', 'IBLOCK_ID'));
            while ($ar = $ob->GetNext()) {
                if ($ar['PROPERTY_TAGS_VALUE'] > 0) {
                    $arTags[] = $ar['PROPERTY_TAGS_VALUE'];
                }
            }
        }
        elseif ($sectionID > 0)
        {
            $ob = CIBlockSection::GetList(array(), array('IBLOCK_ID' => 93, 'ID' => $sectionID), false, array('ID', 'UF_TAGS_ELEMENT'));
            while ($ar = $ob->GetNext()) {
                if (!empty($ar['UF_TAGS_ELEMENT']) && count($ar['UF_TAGS_ELEMENT']) >= self::$minCount) {
                    $arTags = $ar['UF_TAGS_ELEMENT'];
                }
            }
        }
        if(!empty($arTags))
        {
            $arElements = array();
            $arSections = array();
            $arElements = self::GetListElement($arTags);
            foreach ($arTags as $id)
            {

                $arSections = $arSections + self::GetListSection($id);
            }

            $arAll = $arElements + $arSections;
            //print_r2($arTags);
            //print_r2(count($arAll));
            //print_r2($arAll);
            //die();
            $arElementCount = array();

            //нужно найти совпадения тэгов в элементах
            foreach ($arAll as $elID => $item) {
                foreach ($item as $tag) // перебираем каждый тэг элемента
                {
                   foreach ($arAll as $elID2 => $item2) {
                        if ($elID != $elID2) {
                            foreach ($item2 as $tag2) {
                                if ($tag == $tag2)
                                {
                                    $arElementCount[$elID][$elID2][] = $tag;
                                }
                            }
                        }
                    }
                }

            }

            foreach ($arElementCount as $id1 => $item) {
                foreach ($item as $id2 => $item2) {
                    if (count($item2) < self::$minCount) {
                        //print_r2(array($id1, count($item2), $item2));
                        unset($arElementCount[$id1][$id2]);
                    }
                }
            }
            foreach ($arElementCount as $key => $item)
            {
                if(count($item)  < self::$minCount)
                {
                    unset($arElementCount[$key]);
                }
            }
        }
        //print_r2($arElementCount);

        if(!empty($arElementCount))
            $countAdd = self::MaterialsAdd($arElementCount);

        echo 'Найдено совпадений: '.count($arElementCount).'<br>';
        echo 'Добавлено: '.intval($countAdd).'<br>';
        //print_r2($arElementCount);
    }
    public static function GenerateAll()
    {
        ini_set('max_execution_time', 120);
        set_time_limit(120);
        $arTags = self::GetListTags();
        $arElements = self::GetListElement();
        $arSections = self::GetListSection();

        $arAll = $arElements + $arSections;
        $arElementCount = array();

        //нужно найти совпадения тэгов в элементах
        foreach ($arAll as $elID => $item) {
            foreach ($item as $tag) // перебираем каждый тэг элемента
            {
                foreach ($arAll as $elID2 => $item2) {
                    if ($elID != $elID2) {
                        foreach ($item2 as $tag2) {
                            if ($tag == $tag2)
                                $arElementCount[$elID][$elID2][] = $tag;
                        }
                    }
                }
            }

        }

        foreach ($arElementCount as $id1 => $item) {
            foreach ($item as $id2 => $item2) {
                if (count($item2) < self::$minCount) {
                    //print_r2(array($id1, count($item2), $item2));
                    unset($arElementCount[$id1][$id2]);
                }
            }
        }

        self::MaterialsAdd($arElementCount);
       //return $arElementCount;

    }
    public static function Agent()
    {
        self::GenerateAll();
        return "RelatedMaterials::Agent();";
    }
    public static function MaterialsAdd($arrAdd)
    {
        $countAdd = 0;
        $arrMaterials = self::MaterialsGetList();
        $el = new CIBlockElement;
        $arFields = array(
            'NAME' => '0',
            'IBLOCK_ID' => 192,
            'ACTIVE' => 'Y',
            'PROPERTY_VALUES' => array(),
        );

        foreach ($arrAdd as $id => $item) {
            foreach ($item as $id2 => $tags) {
                //print_r2(array($id, $id2, $tags));
                if (strripos($id, 'S') !== false && strripos($id2, 'S') !== false) {

                    $arFields['PROPERTY_VALUES'] = array(
                      'SECTION' => str_replace('S', '', $id),
                      'SECTION2' => str_replace('S', '', $id2),
                      'TAGS' => $tags,
                    );

                    if(empty($arrMaterials['SECTION'][str_replace('S', '', $id)]['S'][str_replace('S', '', $id2)]))
                    {
                        //print_r2($arFields);
                        if($PRODUCT_ID = $el->Add($arFields))
                        {
                            $countAdd++;
                            //print_r2($arFields);
                           // print_r2('ss');
                           //die();
                        }else
                        {
                            echo "Error: ".$el->LAST_ERROR;
                        }
                    }
                }
                elseif (strripos($id, 'S') !== false && strripos($id2, 'S')  === false) {
                    $arFields['PROPERTY_VALUES'] = array(
                      'SECTION' => str_replace('S', '', $id),
                      'ELEMENT2' => $id2,
                      'TAGS' => $tags,
                    );
                    if(empty($arrMaterials['SECTION'][str_replace('S', '', $id)]['E'][$id2]))
                    {
                       // print_r2($arFields);
                        if($PRODUCT_ID = $el->Add($arFields))
                        {
                            $countAdd++;
                            //print_r2($arFields);
                            //print_r2('se');
                            //die();
                        }else
                        {
                            echo "Error: ".$el->LAST_ERROR;
                        }
                    }
                }
                elseif (strripos($id, 'S') === false && strripos($id2, 'S')  !== false) {

                    $arFields['PROPERTY_VALUES'] = array(
                      'ELEMENT' => $id,
                      'SECTION2' =>  str_replace('S', '', $id2),
                      'TAGS' => $tags,
                    );
                    if(empty($arrMaterials['ELEMENT'][$id]['S'][str_replace('S', '', $id2)]))
                    {
                        //print_r2($arFields);
                        if($PRODUCT_ID = $el->Add($arFields))
                        {
                            $countAdd++;
                            //print_r2($arFields);
                            //print_r2('es');
                           // die();
                        }else
                        {
                            echo "Error: ".$el->LAST_ERROR;
                        }
                    }
                }
                elseif (strripos($id, 'S') === false && strripos($id2, 'S')  === false) {
                    $arFields['PROPERTY_VALUES'] = array(
                      'ELEMENT' => $id,
                      'ELEMENT2' => $id2,
                      'TAGS' => $tags,
                    );
                    if(empty($arrMaterials['ELEMENT'][$id]['E'][$id2])) {
                        if ($PRODUCT_ID = $el->Add($arFields)) {
                            $countAdd++;
                            //print_r2('ee');
                            //die();
                        }
                        else
                        {
                            echo "Error: ".$el->LAST_ERROR;
                        }
                    }
                }
                else
                {
                    print_r2('yyy');
                }
            }

        }
        return $countAdd;
    }

    public static function Array($el_id = 0, $sect_id = 0, $count = 150, $active = '')
    {
        $arResult = array();
        if($el_id > 0)
            $filter = array('PROPERTY_ELEMENT2' => $el_id);
        elseif($sect_id > 0)
            $filter = array('PROPERTY_SECTION2' => $sect_id);
        else
        {
            return $arResult;
        }
        if($active == 'Y')
            $filter['ACTIVE'] = 'Y';
        $res = CIBlockElement::GetList(array(), array('IBLOCK_ID' => 192, $filter), false, Array("nPageSize"=>$count), array('PROPERTY_ELEMENT', 'PROPERTY_SECTION', 'PROPERTY_ELEMENT2', 'PROPERTY_SECTION2', 'ID', 'IBLOCK_ID', 'ACTIVE', 'IBLOCK_TYPE'));
        while ($ob = $res->GetNextElement())
        {
            $arFields = $ob->GetFields();
            //print_r2($arFields);
            $ar = $ob->GetProperties();

            $arTags = array();
            $obElTags = CIBlockElement::GetList(array(), array('IBLOCK_ID' => 13, 'ID' => $ar['TAGS']['VALUE']));
            while ($arElTags = $obElTags->GetNext())
            {
                $arTags[] = $arElTags['NAME'];
            }
            $ar['TAGS']['VALUE'] = $arTags;
            //'PROPERTY_TAGS',
            if($ar['ELEMENT']['VALUE'] > 0)
            {
                $arEl = CIBlockElement::GetByID($ar['ELEMENT']['VALUE'])->GetNext();
                //print_r2($arEl);
                if($arEl['ACTIVE'] =='N')
                {
                    continue;
                }
                if($arEl['IBLOCK_ID'] == 162)
                {
                    $db_props = CIBlockElement::GetProperty($arEl['IBLOCK_ID'], $ar['ELEMENT']['VALUE'], array("sort" => "asc"), Array("CODE"=>"SHOW_SITE"));
                    if($ar_props = $db_props->Fetch()) {
                        if($ar_props['VALUE'] == false)
                            continue;
                    }
                }
                $res2 = CIBlock::GetByID($arEl['IBLOCK_ID']);
                if($ar_res = $res2->GetNext())
                    $arEl['IBLOCK_TYPE'] = $ar_res['IBLOCK_TYPE_ID'];
                    $arResult[] = array(
                    'NAME' => $arEl['NAME'],
                    'ACTIVE' => $arFields['ACTIVE'],
                    'IBLOCK_NAME' => $arEl['IBLOCK_NAME'],
                    'IBLOCK_TYPE' => $arEl['IBLOCK_TYPE'],
                    'DETAIL_PAGE_URL' => $arEl['DETAIL_PAGE_URL'],
                    'IBLOCK_ID' => $arEl['IBLOCK_ID'],
                    'ID' => $arEl['ID'],
                    'ID_M' => $arFields['ID'],
                    'TAGS' => $ar['TAGS']['VALUE'] ,
                );
            }
            if($ar['SECTION']['VALUE'] > 0)
            {
                $arSect = CIBlockSection::GetByID($ar['SECTION']['VALUE'])->GetNext();

                if($arSect['ACTIVE'] =='N')
                {
                    continue;
                }

                $res2 = CIBlock::GetByID($arSect['IBLOCK_ID']);
                if($ar_res = $res2->GetNext())
                    $arEl['IBLOCK_TYPE'] = $ar_res['IBLOCK_TYPE_ID'];
                $arResult[] = array(
                    'NAME' => $arSect['NAME'],
                    'ACTIVE' => $arFields['ACTIVE'],
                    'IBLOCK_NAME' => 'Правовая база',
                    'IBLOCK_TYPE' => $arEl['IBLOCK_TYPE'],
                    'DETAIL_PAGE_URL' => $arSect['SECTION_PAGE_URL'],
                    'IBLOCK_ID' => $arSect['IBLOCK_ID'],
                    'ID' => $arSect['ID'],
                    'ID_M' => $arFields['ID'],
                    'TAGS' => $ar['TAGS']['VALUE'],
                );
            }

        }
        return $arResult;
    }
}

\Bitrix\Main\EventManager::getInstance()->addEventHandler("main", "OnAdminIBlockElementEdit", [TabElementRelatedMaterials::getInstance(),'onInit']);
\Bitrix\Main\EventManager::getInstance()->addEventHandler("main", "OnAdminIBlockSectionEdit", [TabElementRelatedMaterials::getInstance(),'onInit']);
class TabElementRelatedMaterials{

    protected static $_instance = null;

    /**
     *
     * @return TabProductElement
     */
    public static function getInstance() {

        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Инициализация и запуск
     * Если не вернёт специальный массив, то прочие методы не запустятся
     * @param array $params Данные по админке
     * @return boolean
     */
    public function onInit($params) {

        $arIb = array(1, 157, 162); // новости, статьи, консультации

        if ($params['IBLOCK']['ID'] != 1 && $params['IBLOCK']['ID'] != 157 && $params['IBLOCK']['ID'] != 162 && $params['IBLOCK']['ID'] != 93) {//Только для инфоблока 4-е
            return false;
        }

        return array(
            "TABSET" => "RELATED_MATERIALS",
            "GetTabs" => array($this, "tabs"),
            "ShowTab" => array($this, "showtab"),
            "Action" => array($this, "action"),
            "Check" => array($this, "check"),
        );
    }

    public function action($params) {
        return true;
    }

    public function check($params) {
        return true;
    }

    /**
     * Возвращает параметры будущей вкладки
     * @param type $arArgs
     * @return type
     */
    public function tabs($arArgs) {

        return array(
            array(
                "DIV" => "related_materials_edit1",
                "TAB" => "Материалы по теме",
                "ICON" => "iblock",
                "TITLE" => "Материалы по теме",
                "SORT" => 100
            )
        );
    }

    /**
     * Вывод нужной вкладки
     * @param type $divName
     * @param type $params
     * @param type $bVarsFromForm
     */
    public function showtab($divName, $params, $bVarsFromForm) {

        //return;

        if ($divName == "related_materials_edit1") {//Одна из наших вкладок созданых в tabs

            if($params['IBLOCK']['ID'] == 93)
            {
                $strID = '0, '.$params['ID'];
                $ar = RelatedMaterials::Array(0, $params['ID'], 500);
            }
            else
            {
                $strID = $params['ID'].', 0';
                $ar = RelatedMaterials::Array($params['ID'], 0, 500);
            }
            ?>

            <tr> <!--Обязательно, что бы не разнесло форму-->
                <td> <!--Обязательно, что бы не разнесло форму-->
                    <button class="adm-btn" type="button" onclick="GenerateMaterials(<?=$strID?>); $(this).remove(); return false;">Сгенерировать материалы по теме</button>
                    <div class="related-status https_check_success"></div><br><br>
                    <table class="internal" width="100%" cellspacing="0" cellpadding="0" border="0">
                        <tbody>
                        <tr class="heading">

                            <td>№</td>
                            <td>Активность</td>
                            <td>Инфоблок</td>
                            <td>Название</td>
                            <td>Тэги</td>
                        </tr>
                        <tr >

                            <td colspan="5"><a href="#" onclick="CheckAll(); return false;">Выбрать все</td>

                        </tr>
                        <?php $i = 0;$s=1; ?>
                        <?php foreach ($ar as $item) { ?>
                            <tr>
                                <td valign="top" align="center">
                                    <?=$s++;?>
                                </td>
                                <td valign="top" align="center" class="check-material">
                                    <input type="checkbox"  onclick="MaterialUpdate(<?=$item['ID_M']?>);" <?=$item['ACTIVE']=='Y'?'checked':''?> value="<?=$item['ID']?>">
                                </td>
                                <td valign="top" align="center">
                                    <?=$item['IBLOCK_NAME']?>
                                </td>
                                <td valign="top" align="left">
                                    <? if($item['IBLOCK_ID'] == 93):?>
                                        <a href="/bitrix/admin/iblock_section_edit.php?IBLOCK_ID=93&type=act&ID=<?=$item['ID']?>&lang=ru&from=iblock_section_admin"><?=$item['NAME']?></a>


                            <? else:?>
                                    <a href="/bitrix/admin/iblock_element_edit.php?IBLOCK_ID=<?=$item['IBLOCK_ID']?>&type=<?=$item['IBLOCK_TYPE']?>&ID=<?=$item['ID']?>&lang=ru&find_section_section=0&WF=Y"><?=$item['NAME']?></a>
                                <?endif;?>
                            </td>
                                <td valign="top" align="center">
                                    <?=implode(', ', $item['TAGS'])?>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </td>
            </tr>

            <?

            global $APPLICATION;
            //CJSCore::Init(array("jquery"));
            $APPLICATION->AddHeadString('<script>
function MaterialUpdate(id) {$.get(\'/local/ajax/related_materials.php\', {id:id});}
function CheckAll(){
    $(".check-material input").each(function ()
    {
        $(this).trigger( "click" );
    })
}
function GenerateMaterials(el, sect){
    $(".related-status").text("Генерация запущена");
    $.get("/dev/generate_tags_id.php", 
    {"el": el, "sect": sect }, 
    function (data)
    {
        $(".related-status").html(data);
    })
    return false;
}
</script>');
        }
    }



}
