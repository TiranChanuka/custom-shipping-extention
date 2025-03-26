jQuery(document).ready(function ($) {
  var deletedRates = [];

  // Add new rate
  $(document).on("click", ".add_rate", function () {
    var $tbody = $("#shipping_rates_table tbody");
    var $lastRow = $tbody.find("tr:last").clone();

    // Clear values in the cloned row
    $lastRow.find("input").val("");
    $lastRow.find("select").prop("selectedIndex", 0);

    // Remove any existing data-rate-id
    $lastRow.removeAttr("data-rate-id");

    // Replace the Add button with Remove button
    $lastRow
      .find(".add_rate")
      .removeClass("add_rate")
      .addClass("remove_rate")
      .text("Remove");

    // Insert before the "new rate" row
    $tbody.find("tr:last").before($lastRow);
  });

  // Remove rate
  $(document).on("click", ".remove_rate", function () {
    var $row = $(this).closest("tr");
    var rateId = $row.attr("data-rate-id");

    // If this is an existing rate (has an ID), add it to deleted rates
    if (rateId) {
      deletedRates.push(rateId);
      $("#deleted_rates").val(deletedRates.join(","));
      console.log("Rate " + rateId + " marked for deletion");
    }

    $row.remove();
  });

  // Debug: Log when form is submitted
  $("form#mainform").on("submit", function () {
    console.log("Form submitted. Deleted rates:", $("#deleted_rates").val());
  });
});
