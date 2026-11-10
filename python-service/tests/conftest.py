import os
import sys
import tempfile

# Configure the service before any `app` module is imported (settings load at import time).
TEST_STORAGE = tempfile.mkdtemp(prefix="dst-storage-")
TEST_INTERNAL_KEY = "test-internal-key-0123456789abcdef"

os.environ["INTERNAL_KEY"] = TEST_INTERNAL_KEY
os.environ["STORAGE_PATH"] = TEST_STORAGE

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
