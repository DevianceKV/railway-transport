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

        // Array of unique stations.
        $stations = $this->getStations($data);     

        // Rendering starting page.
        return $this->render('main/index.html.twig', [
            'stations' => $stations
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

            for ($i = 0; $i < sizeof($line); $i++) {
                if ($ind || $from == $line[$i]) {
                    if (!$ind) {
                        $ind = true;
                    }
                    array_push($path, $line[$i]);
                    if ($to == $line[$i]) {
                        array_push($goodRoutes, $path);
                        break;
                    }
                }
            }
        }

        return $goodRoutes;
    }

    public function getRoutesWithChanges($from, $to, $routes) {
        $goodRoutes = [];
        $starting = [];
        $ending = [];

        foreach ($routes as $line) {
            if (array_search($from, $line) !== false && array_search($to, $line) === false && array_search($from, $line) !== sizeof($line) - 1) {
                $pos = array_search($from, $line);
                $rest = array_slice($line, $pos + 1);
                if (array_search($from, $rest) !== false) {         
                    $first = array_slice($line, 0, $pos + 1);
                    $second = array_slice($line, $pos + 1, sizeof($line) - $pos + 1);
                    array_push($starting, $second);
                    array_push($starting, $first);      
                }
                else {
                    array_push($starting, $line);
                }                  
            }
            else if (array_search($from, $line) === false && array_search($to, $line) !== false) {
                $pos = array_search($to, $line);
                $rest = array_slice($line, $pos + 1);
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

        foreach ($starting as $start) {
            foreach($ending as $end) {
                $ind = false;
                $duo = [];
                $path = [];
                $posS = array_search($from, $start);
                $posE = array_search($to, $end);
                for ($i = $posS + 1; $i < sizeof($start); $i++) {
                    if ($start[$i] == $from) {
                        $posS = $i;
                        continue;
                    }
                    $contains = array_search($start[$i], $end);
                    if ($contains !== false && $contains < $posE) {
                        $partOfStart = array_slice($start, $posS, $i - $posS + 1);
                        $partOfEnd = array_slice($end, $contains, $posE - $contains + 1);
                        array_push($duo, $partOfStart);
                        array_push($duo, $partOfEnd);
                        array_push($goodRoutes, $duo);
                    }
                }
            }
        }
  
        return $goodRoutes;
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

        $directRoutes = $this->getDirectRoutes($fromTo[0], $fromTo[1], $routes);

        $routesWithChanges = $this->getRoutesWithChanges($fromTo[0], $fromTo[1], $routes);   

        $bestRoutes = [];
        $lengthOfBest = 0;

        if (empty($directRoutes) && empty($routesWithChanges)) {
            $allWithChanges = [];   
            $stops = [];

            array_push($stops, $fromTo[0]);
            foreach ($routes as $startLine) {
                $good = [];
                $index = array_search($fromTo[0], $startLine);
                if ($index !== false && $index != sizeof($startLine) - 1) {              
                    array_push($good, $startLine);
                    $a = $this->getHigherLevelRoutes($startLine[$index + 1], $fromTo[0], $fromTo[1], $routes, $good, $stops, 0);
                    array_push($allWithChanges, $a);
                }
            }

            $bestRoutes = $this->makeHigherLevelRoutesForShow($allWithChanges);
            $lengthOfBest = sizeof($bestRoutes[0]);  
        }
        


        return $this->render('main/show.html.twig', [
            'stations'          => $stations,
            'directRoutes'      => $directRoutes,
            'routesWithChanges' => $routesWithChanges,
            'bestRoutes'        => $bestRoutes,
            'lengthOfBest'      => $lengthOfBest,
            'fromTo'            => $fromTo
        ]);
    }

    public function makeHigherLevelRoutesForShow($allWithChanges) {
        $min = -1;
        foreach($allWithChanges[0] as $line) {
            if (sizeof($line) < $min || $min == -1) {
                $min = sizeof($line);
            }
        }

        $goodRoutes = [];
        
        for($i = 0; $i < sizeof($allWithChanges[0]); $i += 2) {
            $current = 0;
            $line = [];
            $partOfRoute = $allWithChanges[0][$i];
            if (sizeof($partOfRoute) == $min) {
                $stations = $allWithChanges[0][$i + 1];      

                while ($current < sizeof($stations) - 1) {
                    $start = array_search($stations[$current], $partOfRoute[$current]);
                    $end = array_search($stations[$current + 1], $partOfRoute[$current]);
                    if ($start > $end) {
                        $partOfRoute[$current] = array_slice($partOfRoute[$current], $end + 1, sizeof($partOfRoute[$current]) - $end);
                        $start = array_search($stations[$current], $partOfRoute[$current]);;
                        $end = array_search($stations[$current + 1], $partOfRoute[$current]);
                    }
                    $temp = array_slice($partOfRoute[$current], $start, $end - $start + 1);
                    $current++;
                    array_push($line, $temp);
                }
                array_push($goodRoutes, $line);
            }
        }

        return $goodRoutes;
    }


    public function getHigherLevelRoutes($next, $from, $to, $routes, $good, $stations, $current) {
        $new = [];
        $ind = false;

        for ($i = array_search($next, $good[$current]); $i < sizeof($good[$current]); $i++) {
            if ($next != $from || !$ind) {
                foreach ($routes as $line) {
                    if (array_search($line, $good) === false && array_search($from, $line) === false){
                        $tempS = array_search($good[$current][$i], $line);
                        if ($tempS !== false && $tempS != (sizeof($line) - 1) ){
                            $check = false;
                            if (array_search($good[$current][$i], $stations) !== false) {
                                continue;
                            }
                            foreach($stations as $station) {
                                if (array_search($station, $line) !== false) {
                                    $count = count(array_keys($line, $station));
                                    if ($count < 2) {
                                        $check = true;
                                    }
                                }
                                if ($check) {
                                    break;
                                }
                            }
                            if (!$check) {
                                $tempE = array_search($to, $line);
                                array_push($good, $line);
                                array_push($stations, $good[$current][$i]);
                                $count = count(array_keys($line, $to));
                                if ($count == 2 && $tempE < $tempS) {
                                    $tempE = $tempS + 1;
                                }
                                      
                                if ($tempE !== false && $tempE > $tempS) {        
                                    array_push($new, $good);
                                    array_push($stations, $to);
                                    array_push($new, $stations);
                                }
                                else if ($tempE === false && $tempS < sizeof($line) - 1) {
                                    $tempWhole = $this->getHigherLevelRoutes($line[$tempS + 1], $from, $to, $routes, $good, $stations, $current + 1);
                                    if ($tempWhole != null)
                                        foreach ($tempWhole as $t) {
                                            if (array_search($t, $new) === false) {
                                                array_push($new, $t);
                                            }
                                        }
                                }
                                array_pop($good);
                                array_pop($stations);
                            }
                        }
                    }       
                }
            }
            else {
                $ind = true;
            }  
        }
        if (empty($new))
            return null;
        return $new;
    }
}


