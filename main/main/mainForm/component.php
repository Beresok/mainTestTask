<? if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
	if(!CModule::IncludeModule("iblock"))
		return false;


	$COL_COUNT = 30;
	$arResult["PROPERTY_LIST"] = array();
	$arResult["PROPERTY_LIST_FULL"] = array();

	$rsIBLockPropertyList = CIBlockProperty::GetList(
		array("sort"=>$arParams['SORT1'],"id"=>$arParams['SORT2']), 
		array("ACTIVE"=>"Y", "IBLOCK_ID"=>$arParams["IBLOCK_ID"])
	);
if ($this->StartResultCache())
{
	// Получаем список свойств iBlock
	while ($arProperty = $rsIBLockPropertyList->GetNext())
	{
		// если свойство - список
		if ($arProperty["PROPERTY_TYPE"] == "L") 
		{
			$rsPropertyEnum = CIBlockProperty::GetPropertyEnum($arProperty["ID"]); // возвращаем варианты значений
			$arProperty["ENUM"] = array(); // обнуляем массив $arProperty["ENUM"]
			while ($arPropertyEnum = $rsPropertyEnum->GetNext()) // пока есть значения
			{
				$arProperty["ENUM"][$arPropertyEnum["ID"]] = $arPropertyEnum; // записываем значения
			}
		}
		// если свойство - (предположительно текст) (#спросить у Саши(что за тип свойства "T"))
		if ($arProperty["PROPERTY_TYPE"] == "T")
		{
			if (empty($arProperty["COL_COUNT"])){
				$arProperty["COL_COUNT"] = "30";
			} 
			if (empty($arProperty["ROW_COUNT"])){
				$arProperty["ROW_COUNT"] = "5";
			}
		}

		if(strlen($arProperty["USER_TYPE"]) > 0 )
		{
			$arUserType = CIBlockProperty::GetUserType($arProperty["USER_TYPE"]);
			if(array_key_exists("GetPublicEditHTML", $arUserType))
				$arProperty["GetPublicEditHTML"] = $arUserType["GetPublicEditHTML"];
			else
				$arProperty["GetPublicEditHTML"] = false;
		}
		else
		{
			$arProperty["GetPublicEditHTML"] = false;
		}

		if (in_array($arProperty["ID"],$arParams["PROPERTY_CODES"])) {
			$arResult["PROPERTY_LIST"][] = $arProperty["ID"];
			$arResult["PROPERTY_LIST_FULL"][$arProperty["ID"]] = $arProperty;
		}
	}

	// Обработка полученых значений
	if (check_bitrix_sessid() && (!empty($_REQUEST["iblock_submit"]) || !empty($_REQUEST["iblock_apply"])))
	{
		$arProperties = $_REQUEST["PROPERTY"]; // Записываем приходящий массив в $arProperties
		$arUpdateValues = array(); // обнуление массива
		$arUpdatePropertyValues = array(); // обнуление массива

		// Запись значений по свойствам
		foreach ($arResult["PROPERTY_LIST"] as $i => $propertyID) 
		{
			$arPropertyValue = $arProperties[$propertyID]; //запись значения из пришедшего массива
			// если id свойства не 0
			if (intval($propertyID) > 0) 
			{
				// если тип свойства не файл
				if ($arResult["PROPERTY_LIST_FULL"][$propertyID]["PROPERTY_TYPE"] != "F") 
				{
					// если свойство множественное
					if ($arResult["PROPERTY_LIST_FULL"][$propertyID]["MULTIPLE"] == "Y") 
					{
						$arUpdatePropertyValues[$propertyID] = array();
						// если значение не массив
						if (!is_array($arPropertyValue))
						{
							$arUpdatePropertyValues[$propertyID][] = $arPropertyValue;
						}
						// Если значение массив
						else
						{
							foreach ($arPropertyValue as $key => $value)
							{
								if (
									$arResult["PROPERTY_LIST_FULL"][$propertyID]["PROPERTY_TYPE"] == "L" && intval($value) > 0
									||
									$arResult["PROPERTY_LIST_FULL"][$propertyID]["PROPERTY_TYPE"] != "L" && !empty($value)
								)
								{
									$arUpdatePropertyValues[$propertyID][] = $value;
								}
							}
						}
					}
					// если свойство не множественное
					else 
					{
						// если свойство не список
						if ($arResult["PROPERTY_LIST_FULL"][$propertyID]["PROPERTY_TYPE"] != "L"){ 
							if ($propertyID == $arParams['ID_PROPERTY_EMAIL']) {
								if (preg_match('/\A[^@]+@([^@\.]+\.)+[^@\.]+\z/', $arPropertyValue[0])) 
								{
									$arUpdatePropertyValues[$propertyID] = $arPropertyValue[0];
								}
								else
								{
									$arResult["ERRORS"][$propertyID] = 'Некорректно заполнено поле '.$arResult["PROPERTY_LIST_FULL"][$propertyID]["NAME"];
								}
							} else {
								$arUpdatePropertyValues[$propertyID] = $arPropertyValue[0];
							}
						}else{
							$arUpdatePropertyValues[$propertyID] = $arPropertyValue;
						}
					}
				}
				// если тип свойства файл
				else 
				{
					$arUpdatePropertyValues[$propertyID] = array();
					foreach ($arPropertyValue as $key => $value)
					{
						$arFile = $_FILES["PROPERTY_FILE_".$propertyID."_".$key];
						$arFile["del"] = $_REQUEST["DELETE_FILE"][$propertyID][$key] == "Y" ? "Y" : "";
						$arUpdatePropertyValues[$propertyID][$key] = $arFile;

						if(($arParams["MAX_FILE_SIZE"] > 0) && ($arFile["size"] > $arParams["MAX_FILE_SIZE"]))
							$arResult["ERRORS"][] = GetMessage("IBLOCK_ERROR_FILE_TOO_LARGE");
					}

					if (empty($arUpdatePropertyValues[$propertyID]))
						unset($arUpdatePropertyValues[$propertyID]);
				}
			}
		}

		// Проверка наличия значений в обязательных свойствах
		foreach ($arResult["PROPERTY_LIST"] as $key => $propertyID) 
		{
			if ($arResult["PROPERTY_LIST_FULL"][$propertyID]['IS_REQUIRED'] == 'Y') 
			{
				$bError = false;
				$propertyValue = intval($propertyID) > 0 ? $arUpdatePropertyValues[$propertyID] : $arUpdateValues[$propertyID];

				// если это пользовательское свойство
				if($arResult["PROPERTY_LIST_FULL"][$propertyID]["USER_TYPE"] != ""){ 
					$arUserType = CIBlockProperty::GetUserType($arResult["PROPERTY_LIST_FULL"][$propertyID]["USER_TYPE"]); 
				} else {
					$arUserType = array();
				}

				// если тип свойства файл
				if ($arResult["PROPERTY_LIST_FULL"][$propertyID]['PROPERTY_TYPE'] == 'F') 
				{
					// Если новый элемент
					if ($arParams["ID"] <= 0)
					{
						$bError = true;
						if(is_array($propertyValue))
						{
							if(array_key_exists("tmp_name", $propertyValue) && array_key_exists("size", $propertyValue))
							{
								if($propertyValue['size'] > 0)
								{
									$bError = false;
								}
							}
							else
							{
								foreach ($propertyValue as $arFile)
								{
									if ($arFile['size'] > 0)
									{
										$bError = false;
										break;
									}
								}
							}
						}
					}
				}
				// если это свойство типа text/html
				elseif(array_key_exists("GetLength", $arUserType)) 
				{
					$len = 0;
					if(is_array($propertyValue) && !array_key_exists("VALUE", $propertyValue))
					{
						foreach($propertyValue as $value)
						{
							if(is_array($value) && !array_key_exists("VALUE", $value))
								foreach($value as $val)
									$len += call_user_func_array($arUserType["GetLength"], array($arResult["PROPERTY_LIST_FULL"][$propertyID], array("VALUE" => $val)));
							elseif(is_array($value) && array_key_exists("VALUE", $value))
								$len += call_user_func_array($arUserType["GetLength"], array($arResult["PROPERTY_LIST_FULL"][$propertyID], $value));
							else
								$len += call_user_func_array($arUserType["GetLength"], array($arResult["PROPERTY_LIST_FULL"][$propertyID], array("VALUE" => $value)));
						}
					}
					elseif(is_array($propertyValue) && array_key_exists("VALUE", $propertyValue))
					{
						$len += call_user_func_array($arUserType["GetLength"], array($arResult["PROPERTY_LIST_FULL"][$propertyID], $propertyValue));
					}
					else
					{
						$len += call_user_func_array($arUserType["GetLength"], array($arResult["PROPERTY_LIST_FULL"][$propertyID], array("VALUE" => $propertyValue)));
					}

					if($len <= 0)
						$bError = true;
					if ($arUpdatePropertyValues[$propertyID]['VALUE']['TEXT'] == $arResult["PROPERTY_LIST_FULL"][$propertyID]["DEFAULT_VALUE"]['TEXT']) {
						$bError = true;
					}
				}
				// обработка множественного свойства или списка iBlock
				elseif ($arResult["PROPERTY_LIST_FULL"][$propertyID]["MULTIPLE"] == "Y" || $arResult["PROPERTY_LIST_FULL"][$propertyID]["PROPERTY_TYPE"] == "L")
				{
					if(is_array($propertyValue))
					{

						$bError = true;
						foreach($propertyValue as $value)
						{

							if(strlen($value) > 0)
							{
								$bError = false;
								break;
							}
						}
					}
					elseif(strlen($propertyValue) <= 0)
					{
						$bError = true;
					}
					else
					{
						foreach ($arResult['PROPERTY_LIST_FULL'][$propertyID]['ENUM'] as $PropEnum) {
							if ($PropEnum['DEF'] == 'Y') 
							{
								$defID = $PropEnum['ID'];
							}
						}
						if ($propertyValue == $defID ) {
							$bError = true;
						}
					}
				}
				// обработка одиночных свойств iBlock
				elseif (is_array($propertyValue) && array_key_exists("VALUE", $propertyValue))
				{
					if(strlen($propertyValue["VALUE"]) <= 0)
						$bError = true;

				}
				elseif (!is_array($propertyValue))
				{
					if(strlen($propertyValue) <= 0)
						$bError = true;
					if ($propertyValue == $arResult['PROPERTY_LIST_FULL'][$propertyID]['DEFAULT_VALUE']) {
						$bError = true;
					}
				}

				if ($bError)
				{
					if (!$arResult["ERRORS"][$propertyID]) {
						$arResult["ERRORS"][$propertyID] = 'Заполните поле "'.$arResult["PROPERTY_LIST_FULL"][$propertyID]["NAME"].'".';
					}
					$arResult["ERRORS_ID"][] = $propertyID;
				}
			}
		}

		// Проверка капчи
		if ($arParams["USE_CAPTCHA"] == "Y" && $arParams["ID"] <= 0)
		{
			if (!$APPLICATION->CaptchaCheckCode($_REQUEST["captcha_word"], $_REQUEST["captcha_sid"]))
			{
				$arResult["ERRORS"][0] = 'Неправильные символы CAPTCHA';
			}
		}

		// если ошибок нет
		if (empty($arResult["ERRORS"]))
		{

			$arUpdateValues["PROPERTY_VALUES"] = $arUpdatePropertyValues;
			$arUpdateValues["ACTIVE"] = "N";

			$oElement = new CIBlockElement();

				// Добавление элемента
				$arUpdateValues["IBLOCK_ID"] = $arParams["IBLOCK_ID"];

				if (strlen($arUpdateValues["DATE_ACTIVE_FROM"]) <= 0)
				{
					$arUpdateValues["DATE_ACTIVE_FROM"] = ConvertTimeStamp(time()+CTimeZone::GetOffset(), "FULL");
				}

					$res = CIBlockElement::GetList(
						Array('ID'=>'desc'), 
						Array("IBLOCK_ID"=>IntVal($arParams['IBLOCK_ID'])), 
						false, 
						Array("nPageSize"=>1), 
						Array("ID"));
					while($ob = $res->GetNextElement())
					{
						$arFields = $ob->GetFields();
					}

				$applicationNumber = ++$arFields['ID'];

				$arUpdateValues["NAME"] = 'Заявка №'.$applicationNumber.'.';

				$sAction = "ADD"; // Добавление

				if (!$arParams["ID"] = $oElement->Add($arUpdateValues, false, true, $arParams["RESIZE_IMAGES"]))
				{
					$arResult["ERRORS"][] = $oElement->LAST_ERROR;
				}
				else
				{
					mail($arParams['EMAIL_TO'], 'Новая заявка №'.$applicationNumber.'.', 'Поступила новая заявка с сайта. Номер заявки: '.$applicationNumber.' нового элемента инфоблока '.$arParams['IBLOCK_ID'].'.');

					mail($arProperties[$arParams['ID_PROPERTY_EMAIL']][0], 'Ваша заявка №'.$applicationNumber.' принята.', 'Ваша заявка принята, ей присвоен номер '.$applicationNumber.' нового элемента инфоблока '.$arParams['IBLOCK_ID'].'.');
				}
		}

		if (empty($arResult["ERRORS"])) // редиректы
		{
			LocalRedirect('/'); // если без ajax, то можно на страницу успеха 
			exit();
		}
	}
		if (!empty($arResult["ERRORS"]))
		{
			// echo '<br>-------------------errors---------------------';
			// echo '<pre>'; print_r($arResult["ERRORS"]); echo '</pre>';
			// echo '<br>-------------------errors---------------------';
		}

		// Использование капчи
		if ($arParams["USE_CAPTCHA"] == "Y" && $arParams["ID"] <= 0) 
		{
			$arResult["CAPTCHA_CODE"] = htmlspecialcharsbx($APPLICATION->CaptchaGetCode());
		}
		$this->includeComponentTemplate();
}
?>