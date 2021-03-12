
# Script that cleans up old builds of all repos of a drone server.

## Usage

```bash

docker run -it --rm \
  -e DRONE_SERVER=https://drone.example.com \
  -e DRONE_TOKEN=yourDroneToken \
  -e DRONE_RETENTION_DAYS=60 \
  joshdvir/drone-cleanup
```

Script is from here: https://gist.github.com/MorrisJobke/ab0cd0c899d691384c67f7f4523e32de
