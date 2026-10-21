namespace DeploymentDashboard.Models;

/// <summary>
/// Runtime facts about the hosting environment. Rendered on the dashboard so that
/// the Azure deployment can be verified directly from the browser.
/// </summary>
public sealed record DeploymentInfo
{
    public required string ApplicationName { get; init; }
    public required string Version { get; init; }
    public required string StudentName { get; init; }
    public required string StudentId { get; init; }
    public required string UnitCode { get; init; }

    public required string HostName { get; init; }
    public required string BaseUrl { get; init; }
    public required string Environment { get; init; }
    public required string OperatingSystem { get; init; }
    public required string Architecture { get; init; }
    public required string Framework { get; init; }
    public required string RuntimeVersion { get; init; }
    public required int ProcessorCount { get; init; }
    public required string WorkingSet { get; init; }

    public required string? WebsiteHostName { get; init; }
    public required string? AppServiceName { get; init; }
    public required string? RegionName { get; init; }
    public required string? SlotName { get; init; }
    public required string? InstanceId { get; init; }

    public required DateTimeOffset ServerTimeUtc { get; init; }
    public required DateTimeOffset StartedAtUtc { get; init; }
    public required TimeSpan Uptime { get; init; }
}
