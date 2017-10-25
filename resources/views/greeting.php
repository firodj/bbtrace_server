<html>
  <head>
    <meta charset="utf-8">
    <title>BBTrace</title>
    <link rel="stylesheet" type="text/css" href="/css/styles.css" />
  </head>
  <body>
    <div id="app">
    </div>
    <svg id="bboxlabel" style="visibility: hidden">
    </svg>
    <script type="text/javascript">
    var env = <?php echo json_encode($env); ?>;
    </script>
    <script src="/js/bundle.js" type="text/javascript"></script>
  </body>
</html>
