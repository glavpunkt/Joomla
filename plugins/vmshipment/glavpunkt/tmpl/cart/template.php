<div class="glavpunkt-shipment-cart-options">
    <input
            type="radio" name="glavpunkt-method" id="glavpunkt-method-courier" value="courier"
        <?php echo($displayData['data']['method'] === 'courier' ? "checked" : ""); ?>
    >
    <label for="glavpunkt-method-courier">Курьерская доставка</label>
    <div class="glavpunkt-method-courier-tab glavpunkt-method-tab">
        <?php include $displayData['data']['basePath'] . "/tmpl/cart/courier.php"; ?>
    </div>
    <br>
    <input
            type="radio" name="glavpunkt-method" id="glavpunkt-method-punkts" value="punkts"
        <?php echo($displayData['data']['method'] === 'punkts' ? "checked" : ""); ?>
    >
    <label for="glavpunkt-method-punkts">Самовывоз из пункта выдачи</label>
    <div class="glavpunkt-method-punkts-tab glavpunkt-method-tab">
        <?php include $displayData['data']['basePath'] . "/tmpl/cart/courier.php"; ?>
    </div>
    <br>
    <input
            type="radio" name="glavpunkt-method" id="glavpunkt-method-post" value="post"
        <?php echo($displayData['data']['method'] === 'post' ? "checked" : ""); ?>
    >
    <label for="glavpunkt-method-post">Доставка почта РФ</label>
    <div class="glavpunkt-method-post-tab glavpunkt-method-tab">
        <?php include $displayData['data']['basePath'] . "/tmpl/cart/courier.php"; ?>
    </div>
</div>

<script type="text/javascript">
    jQuery(function ($) {

        $(document).on('change', 'input[name=glavpunkt-method]', function () {
            var datas = {
                'method': $('input[name=glavpunkt-method]:checked').val(),
            };
            updateCart(window.location.pathname, datas, 'POST');
        });

        $(document).on('change', '.update-cart', function () {
            var datas = {
                'selectedDate': $('#deliveryDate').val(),
                'selectedInterval': $('#deliveryInterval').val(),
                'method': $('input[name=glavpunkt-method]:checked').val(),
            };
            updateCart(window.location.pathname, datas, 'POST');
        });

        $('.glavpunkt-courier').change(function (e, firstCall) {
            var cityTo = jQuery(".glavpunkt-courier option:checked").html();
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
                }
            });
        });
    });
</script>