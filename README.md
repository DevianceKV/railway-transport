# Rail transport app made in PHP Symfony.
Simple app designed to search railway lines.

The idea is that from given railway lines user chooses two stations from list.
When starting and endig station is choosen, choises are displayed.

## How to test

You can find instructions on how to run this app on your computer in [INSTALL.md](https://github.com/DevianceKV/railway-transport/blob/master/INSTALL.md).

## Data

Data is store if [lines.json](https://github.com/DevianceKV/railway-transport/blob/master/public/lines/lines.json) file and are array of objects containing array of stations and a type of line.
There can be two tipes of lines:
* **Double** - if given line is A-B-C, train will travel A->B->C and C->B->A.
* **Round** -  if given line is A-B-C, train will travel A->B->C->A->B->...

Stations in lines.json are represented by places in Serbia.

## User input

User can choose starting and ending station from two dropdown/search menus whose options to choose are lists of all unique stations on lines.
Code for these menus is located in file [index.html.twig](https://github.com/DevianceKV/railway-transport/blob/master/templates/main/index.html.twig).

## Result

Result calculation will be triggerd only when both stations are selected and they are not the same.
All calculations are located in [MainController.php](https://github.com/DevianceKV/railway-transport/blob/master/src/Controller/MainController.php).
If there are routes to display, result will be displayed in tables.
Tables look like:

From | To | Part of route | Part of route | ...
------------ | ------------- | ------------- | ------------- | -------------
Starting station | Ending station | Part of route | Part of route | ...
Starting station | Ending station | Part of route | Part of route | ...

The content of table will be filled with different data, depending on the type of result.
There can be three result types:
1. **Direct routes** -  Both stations are on the same line.
1. **Routes with one change of line** - Stations are on different lines, but made a route with same stations (middle station) in both lines.
1. **Routes with more then one change of line** - Routes with more then one middle station.

Note: Third type of result will only be displayed if there are no results of other types.
Also for this type, only the best results will be displayed.

Code that is used to display tables is located in [show.html.twig](https://github.com/DevianceKV/railway-transport/blob/master/templates/main/show.html.twig).

