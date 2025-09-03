<?php require_once __DIR__ . '/config.php'; ?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Map View — <?= SCHEDULE_NAME ?></title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <style> #map{height: calc(100vh - 60px);} </style>
</head>
<body>
  <div style="padding:8px">
    <input type="date" id="d" value="<?= date('Y-m-d') ?>">
    <button onclick="load()">Load</button>
  </div>
  <div id="map"></div>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    const map = L.map('map').setView([32.7767, -96.7970], 8);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{ maxZoom:19 }).addTo(map);
    let group = L.layerGroup().addTo(map);

    function load(){
      group.clearLayers();
      const d = document.getElementById('d').value;
      fetch(`/095/schedule-ng/api/jobs_by_date_geo.php?date=${d}`).then(r=>r.json()).then(rows=>{
        const pts=[];
        rows.forEach(r=>{
          if(r.lat && r.lng){
            const m = L.marker([r.lat, r.lng]).addTo(group);
            m.bindPopup(`<b>${r.title}</b><br>${r.location}<br>${r.contractor||''}<br>${r.time}`);
            pts.push([r.lat,r.lng]);
          }
        });
        if(pts.length){ map.fitBounds(pts); }
      });
    }
    load();
  </script>
</body>
</html>
