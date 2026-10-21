using System.Runtime.InteropServices;
using DeploymentDashboard.Services;
using Microsoft.Extensions.Configuration;

namespace DeploymentDashboard.Tests;

/// <summary>
/// Tests for the deployment telemetry provider.
///
/// The provider is the part of the application that proves the deployment is
/// real: it reads configuration and the WEBSITE_* variables that Azure App
/// Service injects. These tests pin down the fallbacks that are used when the
/// application runs outside Azure (local development, unit tests, CI).
/// </summary>
[Collection("Environment")]
public sealed class DeploymentInfoProviderTests
{
    private static IConfiguration Config(params (string Key, string Value)[] values) =>
        new ConfigurationBuilder()
            .AddInMemoryCollection(values.Select(v => new KeyValuePair<string, string?>(v.Key, v.Value)))
            .Build();

    [Fact]
    public void Get_ReadsStudentIdentityFromConfiguration()
    {
        var provider = new DeploymentInfoProvider(Config(
            ("App:Name", "CloudDeploy Dashboard"),
            ("App:Version", "2.3.1"),
            ("Student:FullName", "Vi Nguyen"),
            ("Student:Id", "104123456"),
            ("Student:UnitCode", "SWE40006")));

        var info = provider.Get();

        Assert.Equal("CloudDeploy Dashboard", info.ApplicationName);
        Assert.Equal("2.3.1", info.Version);
        Assert.Equal("Vi Nguyen", info.StudentName);
        Assert.Equal("104123456", info.StudentId);
        Assert.Equal("SWE40006", info.UnitCode);
    }

    [Fact]
    public void Get_FallsBackToSafePlaceholdersWhenConfigurationIsMissing()
    {
        var provider = new DeploymentInfoProvider(Config());

        var info = provider.Get();

        Assert.Equal("CloudDeploy Dashboard", info.ApplicationName);
        Assert.Equal("1.0.0", info.Version);
        Assert.Equal("Student Name", info.StudentName);
        Assert.Equal("Student ID", info.StudentId);
        Assert.Equal("SWE40006", info.UnitCode);
    }

    [Fact]
    public void Get_ReportsLiveRuntimeFacts()
    {
        var provider = new DeploymentInfoProvider(Config());

        var info = provider.Get();

        Assert.False(string.IsNullOrWhiteSpace(info.HostName));
        Assert.Equal(RuntimeInformation.ProcessArchitecture.ToString(), info.Architecture);
        Assert.InRange(info.ProcessorCount, 1, 256);
        Assert.True(info.Uptime >= TimeSpan.Zero);
        Assert.True(info.ServerTimeUtc >= info.StartedAtUtc);
    }

    [Fact]
    public void Get_MapsAzureAppServiceEnvironmentVariables()
    {
        const string site = "clouddeploy-test";
        const string region = "australiaeast";

        var originalSite = Environment.GetEnvironmentVariable("WEBSITE_SITE_NAME");
        var originalRegion = Environment.GetEnvironmentVariable("REGION_NAME");
        var originalHost = Environment.GetEnvironmentVariable("WEBSITE_HOSTNAME");

        try
        {
            Environment.SetEnvironmentVariable("WEBSITE_SITE_NAME", site);
            Environment.SetEnvironmentVariable("REGION_NAME", region);
            Environment.SetEnvironmentVariable("WEBSITE_HOSTNAME", "clouddeploy-test.azurewebsites.net");

            var info = new DeploymentInfoProvider(Config()).Get();

            Assert.Equal(site, info.AppServiceName);
            Assert.Equal(region, info.RegionName);
            Assert.Equal("clouddeploy-test.azurewebsites.net", info.WebsiteHostName);
        }
        finally
        {
            Environment.SetEnvironmentVariable("WEBSITE_SITE_NAME", originalSite);
            Environment.SetEnvironmentVariable("REGION_NAME", originalRegion);
            Environment.SetEnvironmentVariable("WEBSITE_HOSTNAME", originalHost);
        }
    }

    [Fact]
    public void Get_ReturnsNullsWhenNotHostedInAzure()
    {
        var originalSite = Environment.GetEnvironmentVariable("WEBSITE_SITE_NAME");

        try
        {
            Environment.SetEnvironmentVariable("WEBSITE_SITE_NAME", null);

            var info = new DeploymentInfoProvider(Config()).Get();

            Assert.Null(info.AppServiceName);
        }
        finally
        {
            Environment.SetEnvironmentVariable("WEBSITE_SITE_NAME", originalSite);
        }
    }
}

/// <summary>
/// Environment variables are process wide, so the tests that mutate them must
/// not run in parallel with anything else.
/// </summary>
[CollectionDefinition("Environment", DisableParallelization = true)]
public sealed class EnvironmentCollection
{
}
