<p>Доставка в город:
    <select style="padding: 4px 5px;" class="glavpunkt-courier" id="courierDeliveryGlavpunkt">
        <?php foreach ($displayData['data']['courier']['cities'] as $city) { ?>
            <option
                    value="<?php echo $city['name']; ?>"
                    data-price=""
                <?php echo($displayData['data']['cityTo'] === $city['name'] ? "selected" : ""); ?>
            >
                <?php echo $city['name']; ?>
            </option>
        <?php } ?>
    </select>
</p>
<p>
    <label for="deliveryDate">Предпочтительная дата доставки</label>
    <input
            class="update-cart"
            type="date"
            name="deliveryDate"
            id="deliveryDate"
            min="<?php echo $displayData['data']['courier']['minDate']; ?>"
            max="<?php echo $displayData['data']['courier']['maxDate']; ?>"
            value="<?php echo $displayData['data']['courier']['selectedDate']; ?>"
    >
</p>
<p>
    <label for="deliveryInterval">Предпочтительный интервал доставки</label>
    <select class="update-cart" name="deliveryInterval" id="deliveryInterval">
        <?php foreach ($displayData['data']['courier']['intervals'] as $interval) { ?>
            <option
                    value="<?php echo $interval; ?>"
                <?php echo($displayData['data']['courier']['selectedInterval'] === $interval ? "selected" : ""); ?>
            >
                <?php echo $interval; ?>
            </option>
        <?php } ?>
    </select>
</p>
