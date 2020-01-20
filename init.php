<?
//подключим файл, который делает экспорт
if (file_exists(__DIR__ . "/include/export_catalog.php"))
	include(__DIR__ . "/include/export_catalog.php");
//подключим функцию экспорта из файла export_catalog.php
csv_export_adm();

//регистрируем обработчик
AddEventHandler("iblock", "OnBeforeIBlockElementUpdate", array("mainTP", "setMainTP"));
AddEventHandler("iblock", "OnBeforeIBlockElementAdd", array("mainTP", "setMainTP"));

class mainTP
{
	//создаем обработчик события
	function setMainTP(&$arFields)
	{
		//если только инфоблок 14(торговые предложения)
		if ($arFields['IBLOCK_ID'] == 14) {
			$mainProduct = CCatalogSku::GetProductInfo(
				$arFields['ID'],
				$arFields['IBLOCK_ID']
			); // $mainProduct['ID'] - это ID товара, $mainProduct['OFFER_IBLOCK_ID'] - это ID инфоблока торговоговые предложения

			$status = 1; //статус Главного торгового предложения. 1 = Да, 0 = Нет
			$arFilter = array(
				'IBLOCK_ID' => $mainProduct['OFFER_IBLOCK_ID'],
				'PROPERTY_' . $mainProduct['SKU_PROPERTY_ID'] => $mainProduct['ID'],
				'PROPERTY_GLAVNOE_TP' => $status
			);

			// Выберем перечень полей, которые нам необходиимы 
			$arSelect = array(
				'ID',
			);

			//получим список торговых предложений с учетом фильтра внутри товара
			$rsOffers = CIBlockElement::GetList(
				array(), //сортировка
				$arFilter, //фильтр данных
				false, //группировка данных
				false, //постраничная навигация
				$arSelect //необходимые поля
			);

			$offer = ''; //переменная для торгового предложения, у которого статус равен 1
			while ($arOffer = $rsOffers->GetNext()) {
				$offer = $arOffer['ID']; //полученное ID торгового предложения занесем в ранее созданную переменную
			}

			$prop_tp_id = 143; //ID свойства "главное торговое предложение"
			foreach ($arFields["PROPERTY_VALUES"][$prop_tp_id] as $prop) {
				if (isset($prop['VALUE'])) {
					if (!empty($prop['VALUE'])) {
						$newStatus = 0; //новый статус
						//с помощью метода SetPropertyValuesEx установим новый статус у свойства GLAVNOE_TP в найденом ранее торговом предложении
						CIBlockElement::SetPropertyValuesEx(
							$offer,
							$mainProduct['OFFER_IBLOCK_ID'],
							array(
								"GLAVNOE_TP" => array(
									"VALUE" => $newStatus
								)
							)
						);
					}
					break;
				}
			}
		}
	}
}
