jQuery(document).ready(function ($) {
  // Add new rate
  $(document).on("click", ".add_rate", function () {
    var $tbody = $("#shipping_rates_table tbody");
    var $lastRow = $tbody.find("tr:last").clone();

    // Clear values in the cloned row
    $lastRow.find("input").val("");
    $lastRow.find("select").prop("selectedIndex", 0);

    // Replace the Add button with Remove button
    $lastRow
      .find(".add_rate")
      .removeClass("add_rate")
      .addClass("remove_rate")
      .text("Remove");

    $tbody.append($lastRow);
  });

  // Remove rate
  $(document).on("click", ".remove_rate", function () {
    $(this).closest("tr").remove();
  });
});
