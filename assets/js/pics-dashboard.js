/**
 * PICS Dashboard Charts
 * ---------------------
 * Data-driven trend charts for the Print Inventory Control System (PICS)
 * dashboard. Every value comes from the database via backend/dashboard_trends.php
 * — there is NO hardcoded/fake data. Charts only render when records exist;
 * otherwise an "empty state" message is shown.
 *
 * Built on ApexCharts (global `ApexCharts`, loaded via apexcharts.min.js).
 */
(function () {
  "use strict";

  const COLORS = {
    primary: "#2b7fff",
    danger: "#ef4444",
    production: "#0d9488",
  };

  class PICSDashboard {
    constructor() {
      this.charts = {};
    }

    /** Show an "empty state" message inside a chart container. */
    showEmpty(el) {
      if (!el) return;
      el.innerHTML =
        '<div style="display:flex;align-items:center;justify-content:center;height:310px;">' +
        '<span style="color:var(--text-default-500,#6b7280);font-size:0.875rem;">No data available</span>' +
        "</div>";
    }

    /**
     * Render (or refresh) a single ApexChart. All 12 months are always shown;
     * months with no record are null so nothing is plotted for them.
     */
    renderChart(selector, categories, data, name, color, unit = " pcs", isMoney = false) {
      const el = document.querySelector(selector);
      if (!el) return;

      const hasAny = (Array.isArray(data) && data.some((v) => v !== null && v !== undefined));

      if (!hasAny) {
        if (this.charts[selector]) {
          this.charts[selector].destroy();
          this.charts[selector] = null;
        }
        this.showEmpty(el);
        return;
      }

      const fmt = isMoney
        ? (val) => "$" + Number(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        : (val) => Math.round(val) + unit;

      const options = {
        series: [{ name: name, data: data }],
        chart: {
          type: "bar",
          height: 310,
          parentHeightOffset: 0,
          toolbar: { show: false },
        },
        colors: [color],
        plotOptions: { bar: { horizontal: false, columnWidth: "50%" } },
        dataLabels: { enabled: false },
        grid: { show: true, padding: { top: -20, right: 0, bottom: 0 } },
        xaxis: {
          categories: categories,
          axisBorder: { show: false },
          axisTicks: { show: false },
        },
        yaxis: { labels: { formatter: fmt } },
        legend: { position: "bottom" },
        tooltip: { y: { formatter: fmt } },
      };

      if (this.charts[selector]) {
        this.charts[selector].updateOptions(
          { xaxis: { categories } },
          false,
          false,
          true
        );
        this.charts[selector].updateSeries([{ name: name, data: data }]);
      } else {
        this.charts[selector] = new ApexCharts(el, options);
        this.charts[selector].render();
      }
    }

    /** Load real data from the backend and render the charts. */
    loadTrends() {
      fetch("backend/dashboard_trends.php", { cache: "no-store" })
        .then((res) => res.json())
        .then((data) => {
          const categories = Array.isArray(data.categories) ? data.categories : [];

          this.renderChart(
            "#inventoryTrendsChart",
            categories,
            Array.isArray(data.inventory) ? data.inventory : [],
            "Net Stock Movement",
            COLORS.primary
          );

          this.renderChart(
            "#wasteTrendsChart",
            categories,
            Array.isArray(data.waste) ? data.waste : [],
            "Waste Quantity",
            COLORS.danger
          );

          this.renderChart(
            "#productionTrendsChart",
            categories,
            Array.isArray(data.production) ? data.production : [],
            "Production Jobs",
            COLORS.production,
            " jobs"
          );
        })
        .catch((err) => console.error("Failed to load dashboard trends:", err));
    }

    /** Initialize the data-driven charts. */
    init() {
      this.loadTrends();
      setInterval(() => this.loadTrends(), 15000);
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    setTimeout(() => {
      new PICSDashboard().init();
    }, 100);
  });
})();