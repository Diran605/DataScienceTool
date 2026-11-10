class ClientError(ValueError):
    """An error caused by the request itself (bad path, bad expression, ...).

    Routers turn these into HTTP 400 responses instead of 500, so Laravel can tell
    "your input is wrong" apart from "the service broke".
    """


class UnsafePathError(ClientError):
    """A file path that would resolve outside the shared storage directory."""


class UnsafeExpressionError(ClientError):
    """A custom filter expression that uses something other than the allowed syntax."""
