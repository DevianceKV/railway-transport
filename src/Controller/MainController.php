<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class MainController extends AbstractController
{
    /**
     * @Route("/", name="main")
     */
    public function index()
    {   
        // Loading lines from json file.
        $linesJson = file_get_contents('../public/lines/lines.json');
        $data = json_decode($linesJson, true);
        $fromTo = null;

        // Array of unique stations.
        $stations = $this->getStations($data);     

        // Rendering starting page.
        return $this->render('main/index.html.twig', [
            'stations'      => $stations,
            'fromTo'        => $fromTo
        ]);
    }

    /**
     * @Route("/{route?}", name="show")
     */
    public function show(Request $request)
    {
        // Loading lines from json file.
        $linesJson = file_get_contents('../public/lines/lines.json');
        $data = json_decode($linesJson, true);

        // Array of unique stations.
        $stations = $this->getStations($data);
        $routes = $this->makeRoutes($data);
        $path = $request->get('route');
        $fromTo = explode("-", $path);

        // Getting all direct routes for starting and ending station.
        $directRoutes = $this->getDirectRoutes($fromTo[0], $fromTo[1], $routes);

        // Getting all routes with one transit for starting and ending station.
        $routesWithChanges = $this->getRoutesWithChanges($fromTo[0], $fromTo[1], $routes);   

        $bestRoutes = [];
        $lengthOfBest = 0;

        // Checking if there are no direct or lines with only one transition,
        // if there are none, we are proceding in calling recursive function for higher level transitions.
        if (empty($directRoutes) && empty($routesWithChanges)) {
            $allWithChanges = [];   
            $stops = [];

            // Pushing starting stations in array of stations user will get on and/or get of the train.
            array_push($stops, $fromTo[0]);

            foreach ($routes as $startLine) {
                $good = [];
                // If from station is in this line, we can start trip from here.
                $index = array_search($fromTo[0], $startLine);
                if ($index !== false && $index != sizeof($startLine) - 1) {              
                    array_push($good, $startLine);
                    
                    // Calling recursive function with next station to check, starting and ending station,
                    // current good path and current good stations and last line in it.
                    $a = $this->getHigherLevelRoutes($startLine[$index + 1], $fromTo[0], $fromTo[1], $routes, $good, $stops, 0);
                    
                    // Checking if returned data exists.
                    if ($a != null) {
                        // Merging all collected data.
                        $allWithChanges = array_merge($allWithChanges, $a);  
                    }                                    
                }
            }

            // If there are results, we are making best of them representative for showing.
            if (!empty($allWithChanges)) {
                $bestRoutes = $this->makeHigherLevelRoutesForShow($allWithChanges);
                $lengthOfBest = sizeof($bestRoutes[0]); 
            }
             
        }
        
        // Rendering new page with tables.
        return $this->render('main/show.html.twig', [
            'stations'          => $stations,
            'directRoutes'      => $directRoutes,
            'routesWithChanges' => $routesWithChanges,
            'bestRoutes'        => $bestRoutes,
            'lengthOfBest'      => $lengthOfBest,
            'fromTo'            => $fromTo
        ]);
    }

    // Fetching all unique stations from json file.
    public function getStations($data) {
        // Function that take just Stops data from object and merges arrays.
        $combine = function($carry, $array) {
            if (!$carry) {
                $carry = [];
            }
            return array_merge($carry, $array["Stops"]);
        };

        // Reduction to all Stops from json with defined function.
        $stations = array_reduce($data["Line"], $combine);

        // Returning distinct and sorted list of stations.
        sort($stations);
        return array_unique($stations);
    }



    // Making real routes based on Type (double, round) from json file.
    public function makeRoutes($data) {
        $newRoutes = [];
        foreach ($data["Line"] as $line) {
            $array = $line["Stops"];
            //Double route eg. A-B-C => ABC and CBA.
            if ($line["Type"] == "double") {
                array_push($newRoutes, $array);
                array_push($newRoutes, array_reverse($array));
            }
            // Round route eg. A-B-C => A-B-C-A-B.
            else {
                $temp = array_slice($array, 0, sizeof($array) - 1);
                $new = array_merge($array, $temp);
                array_push($newRoutes, $new);
            }
        }

        return $newRoutes;
    }

    // Geting routes that have both starting end ending point in them.
    public function getDirectRoutes($from, $to, $routes) {
        $goodRoutes = [];

        foreach ($routes as $line) {   
            // Checking if line has both starting and ending stop.
            if (array_search($from, $line) === false || array_search($to, $line) === false) {
                continue;
            }

            $ind = false;
            $path = [];

            // Looping trough stations
            for ($i = 0; $i < sizeof($line); $i++) {
                // If this station is staring station or it allready passed
                // that station is on good line.
                if ($ind || $from == $line[$i]) {
                    // If we've passed starting station, we can start pushing all the next.
                    if (!$ind) {
                        $ind = true;
                    }
                    array_push($path, $line[$i]);
                    // if we've reached ending station, we can finish with adding them.
                    if ($to == $line[$i]) {
                        array_push($goodRoutes, $path);
                        break;
                    }
                }
            }
        }

        return $goodRoutes;
    }

    // Function with which we get routes with one transition.
    public function getRoutesWithChanges($from, $to, $routes) {
        $goodRoutes = [];
        $starting = [];
        $ending = [];

        // Looping trough all $lines
        foreach ($routes as $line) {
            // If this line contains starting and not ending station, we will check it later.
            if (array_search($from, $line) !== false && array_search($to, $line) === false && array_search($from, $line) !== sizeof($line) - 1) {
                $pos = array_search($from, $line);
                $rest = array_slice($line, $pos + 1);
                $pos = array_search($from, $rest);
                // Checking if round type of line, so we can split it.
                if (array_search($from, $rest) !== false) {         
                    $first = array_slice($line, 0, $pos + 1);
                    $second = array_slice($line, $pos + 1, sizeof($line) - $pos + 1);
                    array_push($starting, $second);
                    array_push($starting, $line);      
                }
                else {
                    array_push($starting, $line);
                }                  
            }
            // If this line contains ending and not station station, we will check it later.
            else if (array_search($from, $line) === false && array_search($to, $line) !== false) {
                $pos = array_search($to, $line);
                $rest = array_slice($line, $pos + 1);
                // Checking if round type of line, so we can split it.
                if (array_search($to, $rest) !== false) {         
                    $first = array_slice($line, 0, $pos + 1);
                    $second = array_slice($line, $pos + 1, sizeof($line) - $pos + 1);
                    array_push($ending, $second);
                    array_push($ending, $first); 
                }
                else {
                    array_push($ending, $line);
                }         
            }           
        }

        // Looping trough all stations with starting we've added
        // and then looping trough all stations with ending station.
        foreach ($starting as $start) {
            foreach($ending as $end) {
                $ind = false;
                $duo = [];
                $path = [];
                $posS = array_search($from, $start);
                $posE = array_search($to, $end);
                // Looping trough all stations that can possibly be in both lines.
                for ($i = $posS + 1; $i < sizeof($start); $i++) {
                    if ($start[$i] == $from) {
                        $posS = $i;
                        continue;
                    }
                    $contains = array_search($start[$i], $end);
                    // If it is transit station.
                    if ($contains !== false && $contains < $posE) {
                        // Slicing lines to parts we will actually show.
                        $partOfStart = array_slice($start, $posS, $i - $posS + 1);
                        $partOfEnd = array_slice($end, $contains, $posE - $contains + 1);
                        array_push($duo, $partOfStart);
                        array_push($duo, $partOfEnd);
                        // If there are lines with one transition, we are pushing them to array of
                        // lines satisfying this condition.
                        if (array_search($goodRoutes, $duo) === false && array_search($goodRoutes, $duo) != sizeof($goodRoutes) - 1) {
                            array_push($goodRoutes, $duo);
                        }
                        
                        // If we've found and pushed good line, we can stop checking.
                        break;
                    }
                }
            }
        }
  
        return $goodRoutes;
    }

    // Recursive function that allows us to find lines with more then one transition.
    public function getHigherLevelRoutes($next, $from, $to, $routes, $good, $stations, $current) {
        $new = [];
        // Indicator showing if we've allready passed station in round lines.
        $ind = false;
        // Indicator showing if we've found route with lowest amount of transitions.
        $foundLowest = false;
        // Indicator showing if this stations could have been reched faster.
        $inStations = false;

        // Looping trough all the stations we are checking for potential transition.
        for ($i = array_search($next, $good[$current]); $i < sizeof($good[$current]); $i++) {
            // Looping trough all added lines so we can check if current staion could have been added faster.
            for ($j = 0; $j < sizeof($good) - 1; $j++) {
                // If it could be added faster, setting the indicator to true, and stoping loop.
                if (array_search($good[$current][$i], $good[$j]) !== false && (array_search($stations[$j], $good[$j]) < array_search($good[$current][$i], $good[$j]) ||  count(array_keys($good[$j], $good[$current][$i])) == 2)) {
                    $inStations = true;
                    break;
                }
            }
            // If it could be faster, we dont need to check anymore.
            if ($inStations == true) {
                break;
            }
            // if this is a round line, or we've not passed same stations.
            if ($next != $from || !$ind) {
                // Looping all potential routes we can jump to.
                foreach ($routes as $line) {
                    // If line is not allready checked and it is not other line with starting station.
                    if (array_search($line, $good) === false && array_search($from, $line) === false){
                        // We are getting the position of current station in that line.
                        $tempS = array_search($good[$current][$i], $line);
                        // If if current station exists in this line, and it is not the last station in in
                        if ($tempS !== false && $tempS != (sizeof($line) - 1) ){
                            // If this station is allready in stations, we dont need to check it.
                            if (array_search($good[$current][$i], $stations) !== false) {
                                continue;
                            }

                            // Saving potential ending station in this line.
                            $tempE = array_search($to, $line);

                            // Adding this line and current station to arrays.
                            array_push($good, $line);
                            array_push($stations, $good[$current][$i]);

                            // If it added a station we dont need, we can remove it.
                            if (sizeof($stations) != sizeof($good)) {
                                unset($stations[sizeof($stations) - 2]);
                                $stations = array_values($stations);
                            }

                            // If it is round line, and has two ending stations in it,
                            // we can check second one later if we need to.
                            $count = count(array_keys($line, $to));
                            if ($count == 2 && $tempE < $tempS) {
                                $tempE = $tempS + 1;
                            }   

                            // If ending station on this line, and that station is after transit station,
                            // we have a good route.
                            if ($tempE !== false && $tempE > $tempS) {   
                                // We can set indicator that will disable deeper recursion (because we have better route).
                                $foundLowest = true;

                                // Pushing ending station to array of transit stations.
                                array_push($stations, $to);

                                // Making a duo of good lines, and transit stations.
                                $duo = [$good, $stations];
                                // Adding duos to array of good ones.
                                array_push($new, $duo);

                            }
                            // If there is no ending staion in next line, and we've not found good route so far,
                            // we are going deeper in recursion.
                            else if ($tempE === false && !$foundLowest) {
                                // Calling recursive function, with next station to check, starting and ending station,
                                // all routes, current good routes, their transit stations and position of last line in it. 
                                $tempWhole = $this->getHigherLevelRoutes($line[$tempS + 1], $from, $to, $routes, $good, $stations, $current + 1);
                                // If we have a good return.
                                if ($tempWhole != null) {
                                    // Looping trough all results.
                                    foreach($tempWhole as $t) {
                                        // If we don't allready have that result, we can add it.
                                        if (array_search($t, $new) === false) {
                                            array_push($new, $t);
                                        }
                                    }
                                }
                            }
                            // After we've finished checking line, we can pop it and its transit station.
                            array_pop($good);
                            array_pop($stations);
                        }
                    }       
                }
            }
            // If we've passed same station in round line, we are setting indicator.
            else {
                $ind = true;
            }  
        }

        // If we have no results, we are returning null.
        if (empty($new))
            return null;
        return $new;
    }

    // From all the higher level transitions we have to choose best, and format them for showing.
    public function makeHigherLevelRoutesForShow($allWithChanges) {
        // Setting minimal transitions to -1 so we cannot get that number of transitions.
        $min = -1;
        // Looping trough all of lines with transitions
        foreach($allWithChanges as $line) {
            // If we don't have a minimal value or number of transitions lower ther all the before.
            if (sizeof($line[0]) < $min || $min == -1) {
                // We are setting mininum to number of transitions of this route.
                $min = sizeof($line[0]);
            }
        }

        $goodRoutes = [];

        // Looping trough all of routes with transitions.
        foreach ($allWithChanges as $route) {
            $line = [];
            // If it has minimal transitions.
            if (sizeof($route[0]) == $min) {
                $current = 0;
                // Looping trough all of lines in route.
                foreach ($route[0] as $part) {
                    // Getting possitions of starting and jumping/ending stations in transition.
                    $start = array_search($route[1][$current], $part);
                    $end = array_search($route[1][$current + 1], $part);
                    // If starting bigger then ending possition, we have a round line.
                    if ($start > $end) {
                        // So we are taking the second part of line with correct order.
                        $part = array_slice($part, $end + 1, sizeof($part) - $end);
                        $start = array_search($route[1][$current], $part);
                        $end = array_search($route[1][$current + 1], $part);
                    }
                    // Slicing part of route user can travel on.
                    $temp = array_slice($part, $start, $end - $start + 1);
                    // Setting position of next station we need to check.
                    $current++;
                    // Pushing good part to good line.
                    array_push($line, $temp);                    
                }
                // Pushing good line to array of good lines.
                array_push($goodRoutes, $line);
            }
        }

        return $goodRoutes;
    }

}