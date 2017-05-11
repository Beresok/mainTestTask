<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

if(!CModule::IncludeModule("iblock"))
	return;

if($arCurrentValues["IBLOCK_ID"] > 0)
{
	$arIBlock = CIBlock::GetArrayByID($arCurrentValues["IBLOCK_ID"]);

	$bWorkflowIncluded = ($arIBlock["WORKFLOW"] == "Y") && CModule::IncludeModule("workflow");
	$bBizproc = ($arIBlock["BIZPROC"] == "Y") && CModule::IncludeModule("bizproc");
}
else
{
	$bWorkflowIncluded = CModule::IncludeModule("workflow");
	$bBizproc = false;
}

$arIBlockType = CIBlockParameters::GetIBlockTypes();

$arIBlock=array();
$rsIBlock = CIBlock::GetList(Array("sort" => "asc"), Array("TYPE" => $arCurrentValues["IBLOCK_TYPE"], "ACTIVE"=>"Y"));
while($arr=$rsIBlock->Fetch())
{
	$arIBlock[$arr["ID"]] = "[".$arr["ID"]."] ".$arr["NAME"];
}

$rsProp = CIBlockProperty::GetList(Array("sort"=>"asc", "name"=>"asc"), Array("ACTIVE"=>"Y", "IBLOCK_ID"=>$arCurrentValues["IBLOCK_ID"]));
while ($arr=$rsProp->Fetch())
{
	$arProperty[$arr["ID"]] = "[".$arr["CODE"]."] ".$arr["NAME"];
	if (in_array($arr["PROPERTY_TYPE"], array("L", "N", "S", "F")))
	{
		$arProperty_LNSF[$arr["ID"]] = "[".$arr["CODE"]."] ".$arr["NAME"];
	}
}

$arComponentParameters = array(
	"PARAMETERS" => array(

		"IBLOCK_TYPE" => array(
			"PARENT" => "DATA_SOURCE",
			"NAME" => 'Тип инфоблока',
			"TYPE" => "LIST",
			//"ADDITIONAL_VALUES" => "Y",
			"VALUES" => $arIBlockType,
			"REFRESH" => "Y",
		),

		"IBLOCK_ID" => array(
			"PARENT" => "DATA_SOURCE",
			"NAME" => 'Инфоблок',
			"TYPE" => "LIST",
			//"ADDITIONAL_VALUES" => "Y",
			"VALUES" => $arIBlock,
			"REFRESH" => "Y",
		),

		"PROPERTY_CODES" => array(
			"PARENT" => "DATA_SOURCE",
			"NAME" => "Необходимые поля",
			"TYPE" => "LIST",
			"MULTIPLE" => "Y",
			"VALUES" => $arProperty_LNSF,
		),

		"SORT1" => array(
			"PARENT" => "DATA_SOURCE",
			"NAME" => 'Сортировка первого уровня (По полю sort)',
			"TYPE" => "LIST",
			"VALUES" => array(
				"asc"=>"ASC",
				"desc"=>"DESC",
			),
		),
		"SORT2" => array(
			"PARENT" => "DATA_SOURCE",
			"NAME" => 'Сортировка второго уровня (По полю id)',
			"TYPE" => "LIST",
			"VALUES" => array(
				"asc"=>"ASC",
				"desc"=>"DESC",
			),
		),
		"EMAIL_TO" => Array(
			"PARENT" => "BASE",
			"NAME" => 'E-mail на который будут приходить оповещения', 
			"TYPE" => "STRING",
			"DEFAULT" => htmlspecialcharsbx(COption::GetOptionString("main", "email_from")), 
		),
		"ID_PROPERTY_EMAIL" => Array(
			"PARENT" => "BASE",
			"NAME" => 'Если вы хотите что бы приходило письмо клиенту, укажите id свойства для e-mail пользователя', 
			"TYPE" => "INT",
		),
	),
);

$arComponentParameters["PARAMETERS"]["USE_CAPTCHA"] = array(
	"PARENT" => "DATA_SOURCE",
	"NAME" => 'Использовать CAPTCHA?',
	"TYPE" => "CHECKBOX",
);


