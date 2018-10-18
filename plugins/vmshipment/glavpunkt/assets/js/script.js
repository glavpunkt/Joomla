if (typeof (updateCart) !== "function") {
    function updateCart(myurl, query, type) {
        jQuery.ajax({
            url: myurl,
            data: query,
            type: type ? type : 'POST',
            beforeSend: function () {
                jQuery(this).vm2front("startVmLoading");
            },
            success: function (datas) {
                if (typeof window._klarnaCheckout !== "undefined") {
                    window._klarnaCheckout(function (api) {
                        console.log(' updateSnippet suspend');
                        api.suspend();
                    });
                }
                var el = jQuery(datas).find(Virtuemart.containerSelector);
                if (!el.length) el = jQuery(datas).filter(Virtuemart.containerSelector);
                if (el.length) {
                    Virtuemart.container.html(el.html());
                    if (Virtuemart.updateImageEventListeners) Virtuemart.updateImageEventListeners();
                    if (Virtuemart.updateChosenDropdownLayout) Virtuemart.updateChosenDropdownLayout();
                }
                jQuery('body').trigger('updateVirtueMartCartModule');
                Virtuemart.isUpdatingContent = false;
                jQuery(this).vm2front("stopVmLoading");
                if (typeof window._klarnaCheckout !== "undefined") {
                    window._klarnaCheckout(function (api) {
                        console.log(' updateSnippet suspend');
                        api.resume();
                    });
                }
            },
            error: function (datas) {
                alert('Error updating cart');
                Virtuemart.isUpdatingContent = false;
                jQuery(this).vm2front("stopVmLoading");
            },
            statusCode: {
                404: function () {
                    Virtuemart.isUpdatingContent = false;
                    jQuery(this).vm2front("stopVmLoading");
                    alert("page not found");
                }
            }

        });
    }

}


