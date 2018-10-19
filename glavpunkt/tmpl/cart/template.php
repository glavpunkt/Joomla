<?php foreach ($displayData['error'] as $error) { ?>
    <p class="glavpunkt_error"><?php echo $error; ?></p>
<?php } ?>
<div class="glavpunkt_shipment_cart_options">
    <input
            type="radio" name="glavpunktMethod" id="glavpunktMethodCourier" value="courier"
        <?php echo($displayData['data']['method'] === 'courier' ? "checked" : ""); ?>
    >
    <label for="glavpunktMethodCourier">Курьерская доставка</label>
    <div
            class="glavpunkt_method_tab"
        <?php echo($displayData['data']['method'] === 'courier' ? "style=\"display:block;\"" : ""); ?>
    >
        <?php include $displayData['data']['basePath'] . "/tmpl/cart/courier.php"; ?>
    </div>
    <br>
    <input
            type="radio" name="glavpunktMethod" id="glavpunktMethodPunkts" value="punkts"
        <?php echo($displayData['data']['method'] === 'punkts' ? "checked" : ""); ?>
    >
    <label for="glavpunktMethodPunkts">Самовывоз из пункта выдачи</label>
    <div
            class="glavpunkt_method_tab"
        <?php echo($displayData['data']['method'] === 'punkts' ? "style=\"display:block;\"" : ""); ?>
    >
        <?php include $displayData['data']['basePath'] . "/tmpl/cart/punkts.php"; ?>
    </div>
    <br>
    <input
            type="radio" name="glavpunktMethod" id="glavpunktMethodPost" value="post"
        <?php echo($displayData['data']['method'] === 'post' ? "checked" : ""); ?>
    >
    <label for="glavpunktMethodPost">Доставка почта РФ</label>
    <div
            class="glavpunkt_method_tab"
        <?php echo($displayData['data']['method'] === 'post' ? "style=\"display:block;\"" : ""); ?>
    >
        <?php include $displayData['data']['basePath'] . "/tmpl/cart/post.php"; ?>
    </div>
</div>

<script type="text/javascript">
    jQuery(function ($) {
        // Изменение метода доставки
        $(document).on('change', 'input[name=glavpunktMethod]', function (e) {
            e.preventDefault();
            var domParent = $(this).parents('.vm-shipment-plugin-single');
            if (domParent.find('[name=virtuemart_shipmentmethod_id]').is(':checked') === false) {
                alert('Для продолжения работы выберите пункт: ' + domParent.find('.vmshipment_name').html());
                return false;
            }
            var datas = {
                'method': $('input[name=glavpunktMethod]:checked').val(),
            };
            updateCart(window.location.pathname, datas, 'POST');
        });

        // Изменение дополнительных параметров
        $(document).on('change', '.update_cart', function () {
            var domParent = $(this).parents('.vm-shipment-plugin-single');
            if (domParent.find('[name=virtuemart_shipmentmethod_id]').is(':checked') === false) {
                alert('Для продолжения работы выберите пункт: ' + domParent.find('.vmshipment_name').html());
                return false;
            }
            var datas = {};
            datas[$(this).attr('name')] = $(this).val();
            updateCart(window.location.pathname, datas, 'POST');
        });

        // Изменение города при выборе курьерской доставки
        $('.glavpunkt_сourier').change(function (e, firstCall) {
            var cityTo = jQuery(".glavpunkt_сourier option:checked").html();
            $.ajax({
                url: "https://glavpunkt.ru/api/get_tarif",
                type: "GET",
                data: {
                    serv: "курьерская доставка",
                    cityFrom: "<?php echo $displayData['data']['cityFrom'];?>",
                    cityTo: cityTo.trim(),
                    weight: "<?php echo $displayData['data']['weight'];?>",
                    price: "<?php echo $displayData['data']['price'];?>",
                    paymentType: "<?php echo $displayData['data']['paymentType'];?>"
                },
                dataType: "json",
                success: function (data) {
                    console.log(data);
                    if (data.result === 'ok') {
                        var datas = {
                            'price': data.tarif,
                            'cityTo': data.cityTo,
                            'period': data.period,
                        };
                        updateCart(window.location.pathname, datas, 'POST');
                    }
                },
                error: function (msg) {
                    alert(msg);
                }
            });
        });
    });
</script>