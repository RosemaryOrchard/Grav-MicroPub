# Grav-MicroPub

This MicroPub endpoint for Grav is written to handle JSON micropub requests. The vast majority of data is stored in the header. As such you will either need to find a theme which presents this information as you like, or create one.

Content should be correctly extracted and put below the YAML, however it is also included within the YAML.

This currently does not handle alt text for photos well - you must either use a theme, or create a theme which renders these images correctly with their alt text.

This endpoint passes all of the 100 and 200 tests available on [micropub.rocks](https://micropub.rocks).