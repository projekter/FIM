<!DOCTYPE html>
<html lang="en">
   <head>
      <meta charset="utf-8">
      <title>{$title}</title>
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <meta http-equiv="X-UA-Compatible" content="IE=edge" />
      <link rel="stylesheet" type="text/css" href="{url '/css/bootstrap.min.css'}" />
      <link rel="stylesheet" type="text/css" href="{url '/css/bootstrap-theme.min.css'}" />
      <link rel="stylesheet" type="text/css" href="{url '/css/style.css'}" />
      <link rel="shortcut icon" type="image/vnd.microsoft.icon" href="{url '/img/favicon.ico'}" />
      <!--[if lt IE 9]>
      <script type="text/javascript" src="{url '/js/html5shiv.js'}"></script>
      <script type="text/javascript" src="{url '/js/respond.min.js'}"></script>
      <![endif]-->
      <script type="text/javascript" src="{url '/js/jquery.min.js'}"></script>
      <script type="text/javascript" src="{url '/js/bootstrap.min.js'}"></script>
   </head>
   <body>
      <nav class="navbar navbar-inverse navbar-fixed-top" role="navigation">
         <div class="container">
            <div class="navbar-header">
               <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
                  <span class="icon-bar"></span>
                  <span class="icon-bar"></span>
                  <span class="icon-bar"></span>
               </button>
               <a class="navbar-brand" href="{url '/tasks'}">
                  <img src="{url 'img/fim-logo.png'}" alt="FIM" />
                  My task list
               </a>
            </div>
            <div class="collapse navbar-collapse">
               <ul class="nav navbar-nav">
                  <li class="active"><a href="{url '/tasks'}">Home</a></li>
               </ul>
            </div><!--/.nav-collapse -->
         </div>
      </nav>
      <div class="container">
         <h1>{$title}</h1>
         {block name='body'}{/block}
         <hr />
         <footer>
            <p>&copy; {date('Y')} &mdash; All rights reserved.</p>
         </footer>
      </div>
   </body>
</html>