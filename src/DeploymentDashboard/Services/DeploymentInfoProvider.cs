using System.Diagnostics;
using System.Globalization;
using System.Runtime.InteropServices;
using DeploymentDashboard.Models;

namespace DeploymentDashboard.Services;

public interface IDeploymentInfoProvider
{
    DeploymentInfo Get();
}

/// <summary>
/// Collects deployment metadata from the running process and from the environment
/// variables that Azure App Service injects (WEBSITE_*, REGION_NAME, ...).
/// </summary>
public sealed class DeploymentInfoProvider : IDeploymentInfoProvider
{
    private static readonly DateTimeOffset StartedAt = DateTimeOffset.UtcNow;
    private readonly IConfiguration _configuration;

    public DeploymentInfoProvider(IConfiguration configuration) => _configuration = configuration;

    public DeploymentInfo Get()
    {
        var process = Process.GetCurrentProcess();
        var now = DateTimeOffset.UtcNow;

        return new DeploymentInfo
        {
            ApplicationName = _configuration["App:Name"] ?? "CloudDeploy Dashboard",
            Version = _configuration["App:Version"] ?? "1.0.0",
            StudentName = _configuration["Student:FullName"] ?? "Student Name",
            StudentId = _configuration["Student:Id"] ?? "Student ID",
            UnitCode = _configuration["Student:UnitCode"] ?? "SWE40006",

            HostName = Environment.MachineName,
            BaseUrl = $"{_configuration["ASPNETCORE_URLS"] ?? "http://localhost"}",
            Environment = System.Environment.GetEnvironmentVariable("ASPNETCORE_ENVIRONMENT") ?? "Production",
            OperatingSystem = RuntimeInformation.OSDescription,
            Architecture = RuntimeInformation.ProcessArchitecture.ToString(),
            Framework = RuntimeInformation.FrameworkDescription,
            RuntimeVersion = System.Environment.Version.ToString(),
            ProcessorCount = System.Environment.ProcessorCount,
            WorkingSet = $"{process.WorkingSet64 / 1024d / 1024d:F1} MB",

            WebsiteHostName = Env("WEBSITE_HOSTNAME"),
            AppServiceName = Env("WEBSITE_SITE_NAME"),
            RegionName = Env("REGION_NAME"),
            SlotName = Env("WEBSITE_SLOT_NAME"),
            InstanceId = Env("WEBSITE_INSTANCE_ID"),

            ServerTimeUtc = now,
            StartedAtUtc = StartedAt,
            Uptime = now - StartedAt
        };
    }

    private static string? Env(string key)
    {
        var value = System.Environment.GetEnvironmentVariable(key);
        return string.IsNullOrWhiteSpace(value) ? null : value;
    }
}
