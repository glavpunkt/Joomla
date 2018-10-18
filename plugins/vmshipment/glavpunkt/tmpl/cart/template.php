<div class="glavpunktShipmentCartOptions">
    <input
            type="radio" name="glavpunktMethod" id="glavpunktMethodCourier" value="courier"
        <?php echo($displayData['data']['method'] === 'courier' ? "checked" : ""); ?>
    >
    <label for="glavpunktMethodCourierethod-courier">Курьерская доставка</label>
    <div class="glavpunktMethodCourierTab glavpunktMethodTab">
        <?php include $displayData['data']['basePath'] . "/tmpl/cart/courier.php"; ?>
    </div>
    <br>
    <input
            type="radio" name="glavpunktMethod" id="glavpunktMethodPunkts" value="punkts"
        <?php echo($displayData['data']['method'] === 'punkts' ? "checked" : ""); ?>
    >
    <label for="glavpunktMethodPunkts">Самовывоз из пункта выдачи</label>
    <div class="glavpunktMethodPunktsTab glavpunktMethodTab">
        <?php include $displayData['data']['basePath'] . "/tmpl/cart/courier.php"; ?>
    </div>
    <br>
    <input
            type="radio" name="glavpunktMethod" id="glavpunktMethodPost" value="post"
        <?php echo($displayData['data']['method'] === 'post' ? "checked" : ""); ?>
    >
    <label for="glavpunktMethodPost">Доставка почта РФ</label>
    <div class="glavpunktMethodPostTab glavpunktMethodTab">
        <?php include $displayData['data']['basePath'] . "/tmpl/cart/courier.php"; ?>
    </div>
</div>

<script type="text/javascript">
    jQuery(function ($) {

        $(document).on('change', 'input[name=glavpunktMethod]', function () {
            var datas = {
                'method': $('input[name=glavpunktMethod]:checked').val(),
            };
            updateCart(window.location.pathname, datas, 'POST');
        });

        $(document).on('change', '.updateСart', function () {
            var datas = {
                'selectedDate': $('#deliveryDate').val(),
                'selectedInterval': $('#deliveryInterval').val(),
                'method': $('input[name=glavpunktMethod]:checked').val(),
            };
            updateCart(window.location.pathname, datas, 'POST');
        });

        $('.glavpunktCourier').change(function (e, firstCall) {
            var cityTo = jQuery(".glavpunktCourier option:checked").html();
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