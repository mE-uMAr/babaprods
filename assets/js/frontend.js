/* eslint-env browser */
/* global jQuery, babaprods_ajax */

;(($) => {
  $(document).ready(() => {
    // Frontend functionality can be added here
    // For example, product quick view, wishlist, etc.

    const babaprods_ajax_data = window.babaprods_ajax || {}

    $(".babaprods-buy-btn").on("click", function (e) {
      // Track affiliate link clicks
      const productId = $(this).data("product-id")

      if (productId && babaprods_ajax_data.ajax_url) {
        $.ajax({
          url: babaprods_ajax_data.ajax_url,
          type: "POST",
          data: {
            action: "babaprods_track_click",
            product_id: productId,
            nonce: babaprods_ajax_data.nonce,
          },
        })
      }
    })
  })
})(window.jQuery)
