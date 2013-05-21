# Introduction
This simple PHP script can be used to test JANUS. JANUS is used by OpenConext
to store information about configured services.

# Configuration
Edit the `janusApiTest.php` script to point to your running JANUS instance and
add some IdP and SP entity IDs, some examples are included. We do not 
(automatically) test all IdPs and SPs because that would take a very long time
in bigger instances.

# Running
Just run `php janusApiTest.php`. It will create a directory `data` under the 
directory you installed the script in to store some working data. The data 
contains the output from the initial run. Subsequent runs will show the 
different data the API returns. This way you can test for example database 
modifications to see if they have no unintended side effects on the responses 
from the API.

In addition this script will make it easy to develop a replacement backend 
providing the same API.
