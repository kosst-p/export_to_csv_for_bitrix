<?php
CModule::IncludeModule('iblock'); // подключение инфоблока
CModule::IncludeModule("catalog"); // подключение каталога

/* Функция, которую запускаем на агенте */
function csv_export_adm()
{
    /* ID инфоблока каталог товаров*/
    $IBLOCK_ID_CAT = 13;

    /* ID инфоблока торговые предложения*/
    $IBLOCK_ID_TP = 14;

    /* Заголовки для CSV*/
    $headers = array(
        "ID",
        "ITEM_TITLE",
        "PRICE",
        //"SALE_PRICE", - это поле для скидок. скидки из инфоблока не получить.
        "FINAL_URL",
        "IMAGE_URL ",
        'ITEM_SUBTITLE'
    );

    /* Разделитель для CSV */
    $delimiter = ";";

    /* Получаем товар из инфоблока торговые предложения */
    /* Выводим перечень полей, которые нам необходиимы */
    $arSelect = array(
        'ID', //тут лежит ID торгового предложения(не товара)
        'IBLOCK_ID',
        'PROPERTY_76_VALUE', //отсюда возьмем ID товара(не торгового предложения)
        'CATALOG_PRICE_1',  //минимальная цена
        'DETAIL_PICTURE', // детальная картинка. картинка из превью торгового предложения PREVIEW_PICTURE
        'CATALOG_AVAILABLE', //доступен ли товар=Y, если количество больше 0
        'PROPERTY_FASOVKA' //отсюда можно взять ID фасовки
    );

    /* Фильтр данных */
    $status = 1; //статус Главного торгового предложения. 1 = Да, 0 = Нет
    $arFilter = array(
        'IBLOCK_ID' => $IBLOCK_ID_TP, //только из инфоблока торговые предложения
        'PROPERTY_GLAVNOE_TP' => $status, //только у которого статус 1
        //'CATALOG_AVAILABLE' => 'Y', //только доступный товар
    );

    /* Получим список товаров из инфоблока*/
    $result = CIBlockElement::GetList(
        array(), //сортировка
        $arFilter, //фильтр данных
        false, //группировка данных
        false, //постраничная навигация
        $arSelect //необходимые поля
    );

    $items = []; // пустой массив для товара

    while ($ob = $result->GetNext()) {
        $ob["IMAGE_URL"] = "https://" . SITE_SERVER_NAME . CFile::GetPath($ob["DETAIL_PICTURE"]); //добавим в масив url к детальной картинки товара
        if ($ob['CATALOG_AVAILABLE'] == 'Y') { //сделаем проверку. если торговое предложение есть в наличии у которого $status=1
            $items[] = $ob; //запишем в пустой массив результат фильтра
        } else { //если торговое предложение у которго есть статус $status=1, но его нет в наличии. выберем то предложение, которое есть в наличии в пределах его фасовки
            $arSelect_2 = array(
                'ID', //тут лежит ID торгового предложения(не товара)
                'IBLOCK_ID', // ID инфоблока
                'PROPERTY_76_VALUE', //отсюда возьмем ID товара(не торгового предложения)
                'CATALOG_PRICE_1',  //минимальная цена
                'DETAIL_PICTURE', //детальная картинка. картинка из превью торгового предложения PREVIEW_PICTURE
                'CATALOG_AVAILABLE', //доступность товара
                'PROPERTY_FASOVKA' //ID фасовки
            );
            /* Фильтр данных */
            $arFilter_2 = array(
                'IBLOCK_ID' => $IBLOCK_ID_TP, //только из инфоблока торговые предложения
                'PROPERTY_76_VALUE_VALUE' => $ob['PROPERTY_76_VALUE_VALUE'],
                'PROPERTY_FASOVKA' => $ob['PROPERTY_FASOVKA_VALUE'],
                'CATALOG_AVAILABLE' => 'Y'
            );

            $result_2 = CIBlockElement::GetList(
                array(), //сортировка
                $arFilter_2, //фильтр данных
                false, //группировка данных
                false, //постраничная навигация
                $arSelect_2 //необходимые поля
            );
            while (($ob_2 = $result_2->GetNext()) && ($ob_2['ID'] != $ob['ID'])) {
                $ob_2["IMAGE_URL"] = "https://" . SITE_SERVER_NAME . CFile::GetPath($ob_2["DETAIL_PICTURE"]); //добавим в масив url к детальной картинки товара
                $items[] = $ob_2; //запишем в пустой массив результат фильтра
                break;
            }
        }
    }

    /* Получим имя товара, ссылку и тип товара из ZAGAVITO на товар и запишем в новый массив*/
    $items_upd = []; //новый пустой массив
    foreach ($items as $item) {
        $result = CIBlockElement::GetByID($item["PROPERTY_76_VALUE_VALUE"]); //из этого свойста получим имя и url на товар
        $zag_avito = CIBlockElement::GetProperty( //применим фильтр. из свойства получим свойство инфоблока ZAGAVITO, где нас будет интересовать VALUE_ENUM
            $IBLOCK_ID_CAT,
            $item["PROPERTY_76_VALUE_VALUE"],
            array(),
            array(
                'CODE' => 'ZAGAVITO'
            )
        );
        if ($ar_res = $result->GetNext()) {
            $item['ITEM_TITLE'] = substr($ar_res['NAME'], 0, 25); //имя элемента запишем в ключ с нужным именем. Оставим в названии только 25символов
            $item['FINAL_URL'] = "https://" . SITE_SERVER_NAME . $ar_res['DETAIL_PAGE_URL']; //ссылку на товар запишем в ключ с нужным именем
        }
        if ($ob = $zag_avito->GetNext()) {
            $item['ITEM_SUBTITLE'] = substr($ob['VALUE_ENUM'], 0, 25); //тип товара запишем в ключ с нужным именем. Оставим в названии типа товара только 25 символов
        }

        $items_upd[] = $item; //сформированный массив запишем 
    }
    //print_r($items_upd);
    $arKeys = ['PROPERTY_76_VALUE_VALUE', 'ITEM_TITLE', 'CATALOG_PRICE_1', 'FINAL_URL', 'IMAGE_URL', 'ITEM_SUBTITLE']; //создадим массив с именами(этим именя будут браться, как новые ключи массива)

    $data = []; //создадим итоговый пустой массив в который соберем все данные
    foreach ($items_upd as $value) {

        $arr = [];

        for ($i = 0, $count = count($arKeys); $i < $count; $i++) {
            $key2 = $arKeys[$i];
            if ($arKeys[$i] == 'CATALOG_PRICE_1') { //переименуем CATALOG_PRICE_1 в PRICE
                $key2 = 'PRICE';
            } else if ($arKeys[$i] == 'PROPERTY_76_VALUE_VALUE') { //переименуем PROPERTY_76_VALUE_VALUE в ID
                $key2 = 'ID';
            }
            $arr[$key2] = isset($value[$arKeys[$i]]) ? $value[$arKeys[$i]] : '';
        }

        $data[] = $arr; //запишем все в массив data
    }

    unset($items, $items_upd, $arKeys, $arr); //удалим ранее созданные массивы, чтобы не забивать память

    //Создадим папку, в которой сохраним наш файл csv
    if (!file_exists($_SERVER["DOCUMENT_ROOT"] . '/export')) {
        mkdir($_SERVER["DOCUMENT_ROOT"] . '/export', 0777, true);
    }
    //Открыть(создать) файл
    $fh = fopen($_SERVER["DOCUMENT_ROOT"] . "/export/filecsv.csv", "w+");

    // BOM для кодировки, чтобы отображалось в UTF 8
    fputs($fh, chr(0xEF) . chr(0xBB) . chr(0xBF));

    //Создаем заголовки в файле csv
    fputcsv($fh, $headers, $delimiter);

    //Создадим тело в файле csv из нашего массива с данными $data
    foreach ($data as $fields) {
        fputcsv($fh, $fields, $delimiter);
    }

    //Закрываем файл
    fclose($fh);

    return "csv_export_adm();";
}
