<!DOCTYPE html>
<html>
	<head>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>Milestone Games List</title>

		<link rel="stylesheet" href="style.css" />

    <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <link href="https://cdn.datatables.net/2.2.2/css/dataTables.bootstrap5.min.css" rel="stylesheet" integrity="sha384-BDXgFqzL/EpYeT/J5XTrxR+qDB4ft42notjpwhZDEjDIzutqmXeImvKS3YPH/WJX" crossorigin="anonymous">
    <link href="https://cdn.datatables.net/responsive/3.0.4/css/responsive.bootstrap5.min.css" rel="stylesheet" integrity="sha384-seyUnB//1QOFEqox9uI7YTLBgz9jBwFRqZvsEPFrTw6NAsFEo70nhBWsQfODqiYA" crossorigin="anonymous">
    <link href="https://cdn.datatables.net/scroller/2.4.3/css/scroller.bootstrap5.min.css" rel="stylesheet" integrity="sha384-cZuy1OHhce2mtyO4CNHRjLhW3qKRgYvz2shDWmr3WQhEEESO+/mLmHmGFFD8kZfO" crossorigin="anonymous">
  
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/2.2.2/js/dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/2.2.2/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/3.0.4/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/3.0.4/js/responsive.bootstrap5.js"></script>
    <script src="https://cdn.datatables.net/scroller/2.4.3/js/dataTables.scroller.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/2.11.7/umd/popper.min.js"></script>
    <script src="https://cdn.datatables.net/plug-ins/2.2.2/filtering/type-based/accent-neutralise.js"></script>

	</head>
 <body>

 <?php
        echo '<font size="6"><p align=center style="margin-top: 10px;">Milestone Games List</p></font>';
		
		//error reporting 
		ini_set('display_errors', 1);
		ini_set('display_startup_errors', 1);
		error_reporting(E_ALL);
		
		// array for games returned from sql query - nick
		$sqlgames = array();
		
		// define sql connection variables - nick
		$conn = mysqli_connect("localhost", "u287639072_games", "uAJ5E9ZFvLtVqmQxaDhkRb", "u287639072_games");
		
		// sql query for games - nick
		$query = "SELECT * FROM gamedata";
		
		// initiate sql query - nick
		$sqlgames = mysqli_query($conn, $query);
	?>
	
	<div class="container mb-3 mt-3">

  <?php 

    //set up our filter data 🌮 

    // code for pulling categories from sql

    // name and define the category query
    
    $catquery = "SELECT DISTINCT TRIM(value) AS value 
    FROM (
        SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(gamecats, ',', n.n), ',', -1) AS value
        FROM gamedata 
        CROSS JOIN (
            SELECT a.N
            FROM (
                SELECT @a:=@a+1 AS N
                FROM (
                    SELECT 1 UNION ALL SELECT 1 UNION ALL SELECT 1 UNION ALL SELECT 1 UNION ALL SELECT 1 UNION ALL SELECT 1 UNION ALL SELECT 1 UNION ALL SELECT 1 UNION ALL SELECT 1 UNION ALL SELECT 1 
                    -- Add more 'SELECT 1' as needed to cover the maximum number of comma-separated values in your 'gamecats' column
                ) AS temp
                JOIN (SELECT @a:=0) AS init
            ) AS a
        ) AS n
        WHERE n.N <= 1 + (LENGTH(gamecats) - LENGTH(REPLACE(gamecats, ',', '')))
    ) AS subquery
    WHERE value <> ''
    ORDER BY value ASC";

    $catresult = mysqli_query($conn, $catquery);

    // Check for catquery errors
    if (!$catresult) {
    die("Query failed: " . mysqli_error($conn));
    }

    // Fetch cat results
    $game_categories = array();
    while ($row = mysqli_fetch_assoc($catresult)) {
    $game_categories[] = $row['value'];
    }

    // Convert cat results to JSON
    $cat_array = json_encode($game_categories);

        // name and define the mechanics query
        $mech_query = "SELECT DISTINCT TRIM(value) AS value 
        FROM (
            SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(gamemechs, ',', n.n), ',', -1) AS value
            FROM gamedata 
            CROSS JOIN (
                SELECT a.N
                FROM (
                    SELECT @a:=@a+1 AS N
                    FROM (
                        SELECT 1 UNION ALL SELECT 1 UNION ALL SELECT 1 UNION ALL SELECT 1 UNION ALL SELECT 1 UNION ALL SELECT 1 UNION ALL SELECT 1 UNION ALL SELECT 1 UNION ALL SELECT 1 UNION ALL SELECT 1 
                        -- Add more 'SELECT 1' as needed to cover the maximum number of comma-separated values in your 'gamemechs' column
                    ) AS temp
                    JOIN (SELECT @a:=0) AS init
                ) AS a
            ) AS n
            WHERE n.N <= 1 + (LENGTH(gamemechs) - LENGTH(REPLACE(gamemechs, ',', '')))
        ) AS subquery
        WHERE value <> ''
        ORDER BY value ASC";
    
        $mechresult = mysqli_query($conn, $mech_query);
    
        // Check for mechanics mechquery errors
        if (!$mechresult) {
        die("Query failed: " . mysqli_error($conn));
        }
    
        // Fetch mech results
        $game_mechanics = array();
        while ($row = mysqli_fetch_assoc($mechresult)) {
        $game_mechanics[] = $row['value'];
        }
    
        // Convert mech results to JSON
        $mech_array = json_encode($game_mechanics);
	
  ?>

  <section class="fiters">
    <div class="row">

        <div class="col-12 col-sm-6 col-md-4">
          <label for="filterPlayerCount">Home many players do you have?
            <select type="number" name="filterPlayerCount" class="form-select" id="filterPlayerCount" style="width:100%;">
              <option value="">Any player count (all games)</option>
			        <option value="999">2 Player only games</option>
              <option value="1">1</option>
              <option value="2">2</option>
              <option value="3">3</option>
              <option value="4">4</option>
              <option value="5">5</option>
              <option value="6">6</option>
              <option value="7">7</option>
              <option value="8">8</option>
              <option value="9">9</option>
              <option value="10">10</option>
              <option value="12">12+</option>
            </select>
          </label>
        </div>

        <div class="col-xs-12 col-sm-6 col-md-4">
          <label for="filterComplexity">How complex of a game do you want to play?
            <select name="filterComplexity" id="filterComplexity" class="form-select" style="width:100%;">
              <option selected value="">Any complexity</option>
              <option value="1">(1) Easy</option>
              <option value="2">(2) Light</option>
              <option value="3">(3) Medium Light</option>
              <option value="4">(4) Medium</option>
			        <option value="5">(5) Medium Heavy</option>
              <option value="6">(6) Heavy</option>
            </select>
          </label>
          <input type="hidden" id="complexityMin" value="0">
          <input type="hidden" id="complexityMax" value="9999">
        </div>

        <div class="col-xs-12 col-sm-6 col-md-4">
          <label for="filterGameTime">How much time do you want to spend playing?
            <select name="filterGameTime" id="filterGameTime" class="form-select" style="width:100%;">
              <option selected value="">Any time</option>
              <option value="1">1-15 minutes</option>
              <option value="2">15-30 minutes</option>
              <option value="3">30 Minutes-1 Hour</option>
              <option value="4">1-2 Hours</option>
              <option value="5">More than 2 hours</option>
            </select>
          </label>
          <input type="hidden" id="min" value="0" />
          <input type="hidden" id="max" value="9999" />
        </div>

        <div class="col-xs-12 col-sm-6 col-md-4">
          <label for="filterCategory">What kind of game do you want to play?
            <select name="filterCategory" id="filterCategory" class="form-select" style="width:100%;">
              <option selected value="">Any category</option>
              <?php 
                // foreach ($gameCategories as $category) {
                foreach ($game_categories as $category) {
                  echo "<option value='".$category."'>".$category."</option>";
                }
              ?>
            </select>
          </label>
        </div>
        <div class="col-xs-12 col-sm-6 col-md-4">
          <label for="filterMechanic">Do you have a favorite game mechanic?
            <select name="filterMechanic" id="filterMechanic" class="form-select" style="width:100%;">
            <option selected value="">Any mechanic</option>
            <?php 
                foreach ($game_mechanics as $mechanic) {
                  echo "<option value='".$mechanic."'>".$mechanic."</option>";
                }
              ?>
            </select>
          </label>
        </div>

        <div class="col-xs-12 col-sm-6 col-md-4  d-flex" style="justify-content: space-between; align-items: center">  
		<label for="resetFilters">Want to start over?<br>
          <!-- <button class="btn btn-outline-primary" onclick="window.location.reload()">Reset Filters</button> -->
          <button id="resetFilters" class="btn btn-outline-primary">Reset Filters</button> 
		  </label>
        </div>
		
    </div>

<div>
	<center><p style="font-size:12px"><br>click game image for more details</p></center>
</div>

<section>
    <hr>
    <!-- Table structure -->
    <table class="display nowrap table table-striped table-bordered table-responsive table-sm mydatatable wrapper loading" style="width: 100%">
        <thead>
            <tr>
                <th class="all text-center">Image</th>
                <th class="all text-center">Name</th>
                <th class="desktop text-center">Min Players</th>
                <th class="desktop text-center">Max Players</th>
                <th class="desktop text-center">Min Time</th>
                <th class="desktop text-center">Max Time</th>
                <th class="desktop text-center">Complexity (1-5)</th>
                <th class="none text-center">Category</th>
                <th class="none text-center">Mechanics</th>
                <th class="none text-center">Info Link</th>
                <th class="none text-center">Rating</th>
                <th class="none text-center">Available to rent</th>
                <th class="none text-center">Rented</th>
            </tr>
        </thead>
        <tbody>
            <?php
                foreach ($sqlgames as $game) { ?>
                    <tr class="game">
                        <td><img src="<?php echo $game['thumb']; ?>" height="100"></td>
                        <td class="game__name"><?php echo $game['name']; ?></td>
                        <td class="text-center"><?php echo $game['minplayers']; ?></td>
                        <td class="text-center"><?php echo $game['maxplayers']; ?></td>
                        <td class="text-center"><?php echo $game['minplaytime']; ?></td>
                        <td class="text-center"><?php echo $game['maxplaytime']; ?></td>
                        <td class="text-center"><?php echo $game['complexity']; ?></td>
                        <td class="text-center"><font size="1"><?php echo $game['gamecats']; ?></font></td>
                        <td class="text-center"><font size="1"><?php echo $game['gamemechs']; ?></font></td>
                        <td class="text-center"><font size="1"><a href="<?php echo $game['bgglink']; ?>" target="_blank"><?php echo $game['bgglink']; ?></a></font></td>
                        <td class="text-center"><?php echo $game['rating']; ?></td>
                        <td class="text-center"><font size="1"><?php echo $game['qty']; ?></font></td>
                        <td class="text-center"><font size="1"><?php echo $game['qtyrented']; ?></font></td>
                    </tr>
            <?php 
              }
            ?>
        </tbody>
        <tfoot>
            <tr>
                <th class="text-center">Image</th>
                <th class="text-center">Name</th>
                <th class="text-center">Min Players</th>
                <th class="text-center">Max Players</th>
                <th class="text-center">Min Time</th>
                <th class="text-center">Max Time</th>
                <th class="text-center">Complexity (1-5)</th>
                <th class="text-center">Category</th>
                <th class="text-center">Mechanics</th>
                <th class="text-center">Info Link</th>
                <th class="text-center">Rating</th>
                <th class="text-center">Available to rent</th>
                <th class="text-center">Rented</th>
            </tr>
        </tfoot>
    </table>
</section>

</div>

<script src="filtersnew.js">
</script>

 </body>
</html>