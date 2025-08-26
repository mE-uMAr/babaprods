/* eslint-env browser */
/* global jQuery, babaprods_admin */

;(($) => {
  $(document).ready(() => {
    // Declare variables before using them
    const babaprods_admin_data = window.babaprods_admin || {}

    // Tab functionality
    $(".babaprods-tab-btn").on("click", function () {
      const tabId = $(this).data("tab")

      $(".babaprods-tab-btn").removeClass("active")
      $(".babaprods-tab-content").removeClass("active")

      $(this).addClass("active")
      $("#" + tabId).addClass("active")
    })

    // Fetch single product
    $("#fetch-product").on("click", function () {
      const productUrl = $("#product-url").val().trim()

      if (!productUrl) {
        showAlert("Please enter a product URL", "error")
        return
      }

      const $btn = $(this)
      const originalText = $btn.text()

      $btn.html('<span class="babaprods-loading"></span> Fetching...').prop("disabled", true)

      $.ajax({
        url: babaprods_admin_data.ajax_url,
        type: "POST",
        data: {
          action: "babaprods_fetch_product",
          product_url: productUrl,
          nonce: babaprods_admin_data.nonce,
        },
        success: (response) => {
          if (response.error) {
            showAlert(response.error, "error")
          } else {
            displayProductDetails(response, "#product-result")
          }
        },
        error: () => {
          showAlert("Failed to fetch product. Please try again.", "error")
        },
        complete: () => {
          $btn.text(originalText).prop("disabled", false)
        },
      })
    })

    // Search products
    $("#search-products-btn").on("click", () => {
      const keyword = $("#search-keyword").val().trim()

      if (!keyword) {
        showAlert("Please enter a search keyword", "error")
        return
      }

      searchProducts(keyword, 1)
    })

    // Search products function
    function searchProducts(keyword, page) {
      page = page || 1
      const $btn = $("#search-products-btn")
      const originalText = $btn.text()

      $btn.html('<span class="babaprods-loading"></span> Searching...').prop("disabled", true)
      $("#search-results").html(
        '<div class="babaprods-loading-container"><span class="babaprods-loading"></span> Loading products...</div>',
      )

      $.ajax({
        url: babaprods_admin_data.ajax_url,
        type: "POST",
        data: {
          action: "babaprods_search_products",
          keyword: keyword,
          page: page,
          size: 20,
          nonce: babaprods_admin_data.nonce,
        },
        success: (response) => {
          if (response.error) {
            showAlert(response.error, "error")
            $("#search-results").empty()
          } else if (response.result && response.result.data) {
            displaySearchResults(response.result.data)
          } else {
            showAlert("No products found", "error")
            $("#search-results").empty()
          }
        },
        error: () => {
          showAlert("Search failed. Please try again.", "error")
          $("#search-results").empty()
        },
        complete: () => {
          $btn.text(originalText).prop("disabled", false)
        },
      })
    }

    // Display search results
    function displaySearchResults(data) {
      const products = data.products || []
      const pagination = data.pagination || {}

      let html = ""

      products.forEach((product) => {
        const imageUrl =
          product.image && product.image.main_image
            ? product.image.main_image.startsWith("//")
              ? "https:" + product.image.main_image
              : product.image.main_image
            : "data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMjAwIiBmaWxsPSIjRjlGQUZCIi8+CjxwYXRoIGQ9Ik0xMDAgNzBMMTMwIDEzMEg3MEwxMDAgNzBaIiBmaWxsPSIjRTVFN0VCIi8+Cjwvc3ZnPgo="

        html +=
          '<div class="babaprods-product-card" data-product-id="' +
          product.product_id +
          '">' +
          '<img src="' +
          imageUrl +
          '" alt="Product" class="babaprods-product-image" onerror="this.src=\'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMjAwIiBmaWxsPSIjRjlGQUZCIi8+CjxwYXRoIGQ9Ik0xMDAgNzBMMTMwIDEzMEg3MEwxMDAgNzBaIiBmaWxsPSIjRTVFN0VCIi8+Cjwvc3ZnPgo=\'">' +
          '<div class="babaprods-product-info">' +
          '<div class="babaprods-product-title">' +
          escapeHtml(product.title || "Product #" + product.product_id) +
          "</div>" +
          '<div class="babaprods-product-price">' +
          (product.price || "Price not available") +
          "</div>" +
          '<div class="babaprods-product-id">ID: ' +
          product.product_id +
          "</div>" +
          "</div>" +
          "</div>"
      })

      $("#search-results").html(html)

      // Display pagination
      displayPagination(pagination)

      // Add click handlers for product cards
      $(".babaprods-product-card").on("click", function () {
        const productId = $(this).data("product-id")
        const productUrl = "https://www.alibaba.com/product-detail/_" + productId + ".html"
        fetchProductForModal(productUrl)
      })
    }

    // Display pagination
    function displayPagination(pagination) {
      if (!pagination.page_count || pagination.page_count <= 1) {
        $("#search-pagination").empty()
        return
      }

      let html = ""
      const current = pagination.current
      const total = pagination.page_count

      // Previous button
      if (current > 1) {
        html +=
          "<button onclick=\"window.babaprodsSearchProducts('" +
          $("#search-keyword").val() +
          "', " +
          (current - 1) +
          ')">Previous</button>'
      }

      // Page numbers
      for (let i = Math.max(1, current - 2); i <= Math.min(total, current + 2); i++) {
        const activeClass = i === current ? "active" : ""
        html +=
          '<button class="' +
          activeClass +
          '" onclick="window.babaprodsSearchProducts(\'' +
          $("#search-keyword").val() +
          "', " +
          i +
          ')">' +
          i +
          "</button>"
      }

      // Next button
      if (current < total) {
        html +=
          "<button onclick=\"window.babaprodsSearchProducts('" +
          $("#search-keyword").val() +
          "', " +
          (current + 1) +
          ')">Next</button>'
      }

      $("#search-pagination").html(html)
    }

    // Fetch product for modal
    function fetchProductForModal(productUrl) {
      $("#modal-product-details").html(
        '<div class="babaprods-loading-container"><span class="babaprods-loading"></span> Loading product details...</div>',
      )
      $("#product-modal").show()

      $.ajax({
        url: babaprods_admin_data.ajax_url,
        type: "POST",
        data: {
          action: "babaprods_fetch_product",
          product_url: productUrl,
          nonce: babaprods_admin_data.nonce,
        },
        success: (response) => {
          if (response.error) {
            $("#modal-product-details").html(
              '<div class="babaprods-alert babaprods-alert-error">' + response.error + "</div>",
            )
          } else {
            displayProductDetails(response, "#modal-product-details")
          }
        },
        error: () => {
          $("#modal-product-details").html(
            '<div class="babaprods-alert babaprods-alert-error">Failed to load product details</div>',
          )
        },
      })
    }

    // Display product details
    function displayProductDetails(product, container) {
      const images = product.images || []
      const skus = product.skus || []
      const firstSku = skus[0] || {}
      const ladderPrice = firstSku.ladder_price || []
      const minPrice = ladderPrice.length > 0 ? ladderPrice[0].price : "N/A"
      const maxPrice = ladderPrice.length > 1 ? ladderPrice[ladderPrice.length - 1].price : minPrice

      let galleryHtml = ""
      images.forEach((imageUrl) => {
        if (imageUrl && imageUrl.trim()) {
          const fixedUrl = imageUrl.startsWith("//") ? "https:" + imageUrl : imageUrl
          galleryHtml +=
            '<div class="babaprods-image-preview"><img src="' +
            fixedUrl +
            '" alt="Product Image" onerror="this.parentElement.style.display=\'none\'"></div>'
        }
      })

      const html =
        '<div class="babaprods-product-details">' +
        '<div class="babaprods-product-gallery">' +
        galleryHtml +
        "</div>" +
        '<form class="babaprods-product-form" id="product-form">' +
        '<div class="babaprods-form-group">' +
        "<label>Product Title</label>" +
        '<input type="text" name="title" value="' +
        escapeHtml(product.title || "") +
        '" class="babaprods-input">' +
        "</div>" +
        '<div class="babaprods-form-row">' +
        '<div class="babaprods-form-group">' +
        "<label>Price Range (PKR)</label>" +
        '<input type="text" name="price_range" value="PKR ' +
        minPrice +
        " - PKR " +
        maxPrice +
        '" class="babaprods-input" readonly>' +
        "</div>" +
        "</div>" +
        '<div class="babaprods-form-group">' +
        "<label>Supplier</label>" +
        '<input type="text" name="supplier" value="' +
        escapeHtml(product.supplier || "") +
        '" class="babaprods-input" readonly>' +
        "</div>" +
        '<div class="babaprods-form-group">' +
        "<label>Affiliate Link</label>" +
        '<input type="url" name="affiliate_link" value="' +
        (product.affiliate_link || "") +
        '" class="babaprods-input" readonly>' +
        "</div>" +
        '<div class="babaprods-form-group">' +
        "<label>Button Text</label>" +
        '<input type="text" name="button_text" value="Buy on Alibaba" class="babaprods-input">' +
        "</div>" +
        '<div class="babaprods-form-group">' +
        "<label>Category</label>" +
        '<div class="babaprods-category-container">' +
        '<input type="text" name="category" id="product-category" value="" class="babaprods-input" readonly>' +
        '<button type="button" id="generate-category" class="babaprods-btn babaprods-btn-secondary">Generate Category</button>' +
        '<button type="button" id="retry-category" class="babaprods-btn babaprods-btn-secondary" style="display:none;">Retry</button>' +
        "</div>" +
        "</div>" +
        '<div class="babaprods-form-group">' +
        "<label>Description</label>" +
        '<div class="babaprods-description-container">' +
        '<textarea name="description" rows="4" class="babaprods-textarea" readonly>' +
        escapeHtml(stripHtml(product.description || "")) +
        "</textarea>" +
        '<button type="button" id="regenerate-description" class="babaprods-btn babaprods-btn-secondary">Regenerate Description</button>' +
        '<button type="button" id="retry-description" class="babaprods-btn babaprods-btn-secondary" style="display:none;">Retry</button>' +
        "</div>" +
        "</div>" +
        '<button type="submit" class="babaprods-btn babaprods-btn-highlight">Add to Store</button>' +
        "</form>" +
        "</div>"

      $(container).html(html)

      $("#regenerate-description, #retry-description").on("click", function () {
        const originalDescription = stripHtml(product.description || "")

        if (!originalDescription.trim()) {
          showAlert("No original description available to regenerate", "error")
          return
        }

        const $btn = $(this)
        const originalText = $btn.text()

        $btn.html('<span class="babaprods-loading"></span> Regenerating...').prop("disabled", true)

        $.ajax({
          url: babaprods_admin_data.ajax_url,
          type: "POST",
          data: {
            action: "babaprods_regenerate_description",
            description: originalDescription,
            nonce: babaprods_admin_data.nonce,
          },
          success: (response) => {
            if (response.description) {
              $('textarea[name="description"]').val(response.description)
              $("#retry-description").show()
              showAlert("Description regenerated successfully!", "success")
            } else {
              showAlert(response.error || "Failed to regenerate description", "error")
              $("#retry-description").show()
            }
          },
          error: () => {
            showAlert("Failed to regenerate description. Please try again.", "error")
            $("#retry-description").show()
          },
          complete: () => {
            $btn.text(originalText).prop("disabled", false)
          },
        })
      })

      $("#generate-category, #retry-category").on("click", function () {
        const description = $('textarea[name="description"]').val()

        if (!description.trim()) {
          showAlert("Please enter a description first", "error")
          return
        }

        const $btn = $(this)
        const originalText = $btn.text()

        $btn.html('<span class="babaprods-loading"></span> Generating...').prop("disabled", true)

        $.ajax({
          url: babaprods_admin_data.ajax_url,
          type: "POST",
          data: {
            action: "babaprods_generate_category",
            description: description,
            nonce: babaprods_admin_data.nonce,
          },
          success: (response) => {
            if (response.category) {
              $("#product-category").val(response.category)
              $("#retry-category").show()
              showAlert("Category generated successfully!", "success")
            } else {
              showAlert(response.error || "Failed to generate category", "error")
              $("#retry-category").show()
            }
          },
          error: () => {
            showAlert("Failed to generate category. Please try again.", "error")
            $("#retry-category").show()
          },
          complete: () => {
            $btn.text(originalText).prop("disabled", false)
          },
        })
      })

      // Handle form submission
      $("#product-form").on("submit", function (e) {
        e.preventDefault()
        addProductToStore(product, $(this))
      })
    }

    function addProductToStore(originalProduct, $form) {
      const formData = {}
      $form.find("input, textarea").each(function () {
        formData[$(this).attr("name")] = $(this).val()
      })

      const productData = $.extend({}, originalProduct, {
        title: formData.title,
        button_text: formData.button_text,
        description: formData.description,
        category: formData.category,
        images: originalProduct.images || [],
      })

      const $btn = $form.find('button[type="submit"]')
      const originalText = $btn.text()

      $btn.html('<span class="babaprods-loading"></span> Adding...').prop("disabled", true)

      $.ajax({
        url: babaprods_admin_data.ajax_url,
        type: "POST",
        data: {
          action: "babaprods_add_to_store",
          product_data: JSON.stringify(productData),
          nonce: babaprods_admin_data.nonce,
        },
        success: (response) => {
          if (response.success) {
            showAlert("Product added to store successfully!", "success")
            $("#product-modal").hide()
          } else {
            showAlert(response.data || "Failed to add product to store", "error")
          }
        },
        error: () => {
          showAlert("Failed to add product to store. Please try again.", "error")
        },
        complete: () => {
          $btn.text(originalText).prop("disabled", false)
        },
      })
    }

    // Save credentials
    $("#credentials-form").on("submit", function (e) {
      e.preventDefault()

      const credentials = $("#credentials-json").val().trim()

      if (!credentials) {
        showAlert("Please enter credentials", "error")
        return
      }

      const $btn = $(this).find('button[type="submit"]')
      const originalText = $btn.text()

      $btn.html('<span class="babaprods-loading"></span> Saving...').prop("disabled", true)

      $.ajax({
        url: babaprods_admin_data.ajax_url,
        type: "POST",
        data: {
          action: "babaprods_save_credentials",
          credentials: credentials,
          nonce: babaprods_admin_data.nonce,
        },
        success: (response) => {
          if (response.success) {
            showAlert("Credentials saved successfully!", "success")
            setTimeout(() => {
              location.reload()
            }, 1500)
          } else {
            showAlert(response.data || "Failed to save credentials", "error")
          }
        },
        error: () => {
          showAlert("Failed to save credentials. Please try again.", "error")
        },
        complete: () => {
          $btn.text(originalText).prop("disabled", false)
        },
      })
    })

    // Refresh token
    $("#refresh-token").on("click", function () {
      const $btn = $(this)
      const originalText = $btn.text()

      $btn.html('<span class="babaprods-loading"></span> Refreshing...').prop("disabled", true)

      $.ajax({
        url: babaprods_admin_data.ajax_url,
        type: "POST",
        data: {
          action: "babaprods_refresh_token",
          nonce: babaprods_admin_data.nonce,
        },
        success: (response) => {
          if (response.success) {
            showAlert("Token refreshed successfully!", "success")
            setTimeout(() => {
              location.reload()
            }, 1500)
          } else {
            showAlert("Failed to refresh token", "error")
          }
        },
        error: () => {
          showAlert("Failed to refresh token. Please try again.", "error")
        },
        complete: () => {
          $btn.text(originalText).prop("disabled", false)
        },
      })
    })

    // Modal functionality
    $(".babaprods-close").on("click", () => {
      $("#product-modal").hide()
    })

    $(window).on("click", (e) => {
      if (e.target.id === "product-modal") {
        $("#product-modal").hide()
      }
    })

    // Utility functions
    function showAlert(message, type) {
      const alertClass = type === "success" ? "babaprods-alert-success" : "babaprods-alert-error"
      const alertHtml = '<div class="babaprods-alert ' + alertClass + '">' + message + "</div>"

      // Remove existing alerts
      $(".babaprods-alert").remove()

      // Add new alert
      $(".babaprods-container").prepend(alertHtml)

      // Auto remove after 5 seconds
      setTimeout(() => {
        $(".babaprods-alert").fadeOut()
      }, 5000)
    }

    function escapeHtml(text) {
      const div = document.createElement("div")
      div.textContent = text
      return div.innerHTML
    }

    function stripHtml(html) {
      const div = document.createElement("div")
      div.innerHTML = html
      return div.textContent || div.innerText || ""
    }

    // Make searchProducts globally available
    window.babaprodsSearchProducts = searchProducts
  })
})(window.jQuery)
